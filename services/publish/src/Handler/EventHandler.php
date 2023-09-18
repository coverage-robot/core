<?php

namespace App\Handler;

use App\Service\Publisher\MessagePublisherService;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;
use Bref\Event\Sqs\SqsRecord;
use JsonException;
use Packages\Models\Enum\PublishableMessage;
use Packages\Models\Model\PublishableMessage\PublishableMessageCollection;
use Packages\Models\Model\PublishableMessage\PublishableMessageInterface;
use Symfony\Component\Serializer\SerializerInterface;

class EventHandler extends SqsHandler
{
    public function __construct(
        private readonly MessagePublisherService $messagePublisherService,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @throws InvalidLambdaEvent
     * @throws JsonException
     */
    public function handleSqs(SqsEvent $event, Context $context): void
    {
        $messages = $this->getLatestPublishableMessages($event->getRecords());

        /**
         * @var PublishableMessageInterface[] $messages
         */
        $messages = array_reduce(
            $messages,
            function (array $messages, PublishableMessageInterface $message) {
                if ($message->getType() === PublishableMessage::Collection) {
                    /** @var PublishableMessageCollection $message */
                    return array_merge($messages, $message->getMessages());
                }

                return array_merge($messages, [$message]);
            },
            []
        );

        foreach ($messages as $message) {
            $this->messagePublisherService->publish($message);
        }
    }

    /**
     * Turn a list of SQS records from a Fifo queue into the distinct set of messages which need to be
     * published by this execution.
     *
     * This _has to_ be from a FIFO queue, as we're going to use the message group ID (which will be
     * a unique hash for the owner/repository) to collect a bunch of publishable messages from varying
     * analysis runs, and make sure to _only_ publish the most recent results to the version control provider.
     *
     * Effectively trying to make publishing messages atomic.
     *
     * For example, this handles situations with race conditions, where two competing uploads manage
     * to make results out of order (where an earlier upload, which doesnt include tags from later uploads
     * manages to end up published _after_ a later upload which contains all of the information).
     *
     * @param SqsRecord[] $records
     * @return PublishableMessageInterface[]
     * @throws JsonException
     */
    private function getLatestPublishableMessages(array $records): array
    {
        $messages = [];

        foreach ($records as $record) {
            /** @var array{MessageGroupId: string} $attributes */
            $attributes = $record->toArray()['attributes'];

            $newMessage = $this->serializer->deserialize(
                $record->getBody(),
                PublishableMessageInterface::class,
                'json'
            );
            $currentNewestMessage = $messages[$attributes['MessageGroupId']] ?? null;

            if (!$currentNewestMessage) {
                // This is the first set of messages to publish for this owner/repository
                $messages[$attributes['MessageGroupId']] = $newMessage;
                continue;
            }

            if ($currentNewestMessage->getValidUntil() < $newMessage->getValidUntil()) {
                // This set of messages is more up to date than the current set of messages
                $messages[$attributes['MessageGroupId']] = $newMessage;
            }
        }

        return $messages;
    }
}
