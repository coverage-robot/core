<?php

namespace App\Handler;

use App\Service\MessagePublisherService;
use App\Service\MessagePublisherServiceInterface;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;
use Bref\Event\Sqs\SqsRecord;
use JsonException;
use Override;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Message\Exception\InvalidMessageException;
use Packages\Message\PublishableMessage\PublishableMessageCollection;
use Packages\Message\Service\MessageValidationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;

final class EventHandler extends SqsHandler
{
    public function __construct(
        #[Autowire(service: MessagePublisherService::class)]
        private readonly MessagePublisherServiceInterface $messagePublisherService,
        private readonly SerializerInterface $serializer,
        private readonly MessageValidationService $messageValidationService,
        private readonly LoggerInterface $eventHandlerLogger,
    ) {
    }

    /**
     * @throws InvalidLambdaEvent
     * @throws JsonException
     */
    #[Override]
    public function handleSqs(SqsEvent $event, Context $context): void
    {
        $messages = $this->getLatestPublishableMessages($event->getRecords());

        /**
         * @var PublishableMessageInterface[] $messages
         */
        $messages = array_reduce(
            $messages,
            static function (array $messages, PublishableMessageInterface $message): array {

                if ($message instanceof PublishableMessageCollection) {
                    return [...$messages, ...$message->getMessages()];
                }

                return [...$messages, $message];
            },
            []
        );

        foreach ($messages as $message) {
            $successful = $this->messagePublisherService->publish($message);

            if (!$successful) {
                $this->eventHandlerLogger->error(
                    sprintf(
                        'Failed to publish %s',
                        (string)$message
                    ),
                    [
                        'message' => $message
                    ]
                );
            }
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
                \Packages\Message\PublishableMessage\PublishableMessageInterface::class,
                'json'
            );

            if (!$this->isValid($newMessage)) {
                // The message failed validation, so lets filter it out and log the exception
                continue;
            }

            $currentNewestMessage = $messages[$attributes['MessageGroupId']] ?? null;

            if ($currentNewestMessage === null) {
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

    private function isValid(PublishableMessageInterface $message): bool
    {
        try {
            $this->messageValidationService->validate($message);

            return true;
        } catch (InvalidMessageException $invalidMessageException) {
            $this->eventHandlerLogger->error(
                sprintf(
                    'Failed to validate message %s',
                    (string)$message
                ),
                [
                    'message' => $message,
                    'exception' => $invalidMessageException
                ]
            );
        }

        return false;
    }
}
