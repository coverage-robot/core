<?php

namespace Packages\Models\Model\PublishableMessage;

use Countable;
use DateTimeInterface;
use InvalidArgumentException;
use Packages\Models\Enum\PublishableMessage;
use Packages\Models\Model\Event\EventInterface;
use Packages\Models\Model\Event\GenericEvent;

class PublishableMessageCollection implements PublishableMessageInterface, Countable
{
    private readonly array $messages;

    /**
     * @param PublishableMessageInterface[] $messages
     */
    public function __construct(
        private readonly EventInterface $event,
        array $messages,
    ) {
        $this->messages = array_filter(
            $messages,
            static fn(mixed $message) => $message instanceof PublishableMessageInterface
        );
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    public function getValidUntil(): DateTimeInterface
    {
        return max(
            array_map(
                static fn(PublishableMessageInterface $message) => $message->getValidUntil(),
                $this->messages
            )
        );
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::Collection;
    }

    public static function from(array $data): self
    {
        if (!isset($data['event'], $data['messages'])) {
            throw new InvalidArgumentException('Invalid message provided.');
        }

        $messages = array_filter(
            array_map(
                static fn(array $message) => self::tryFromMessageUsingType($message),
                $data['messages']
            )
        );

        if (count($messages) !== count($data['messages'])) {
            throw new InvalidArgumentException('At least one invalid message has been provided.');
        }

        return new self(
            GenericEvent::from($data['event']),
            $messages
        );
    }

    public static function fromMessageUsingType(array $message): PublishableMessageInterface|null
    {
        return match (PublishableMessage::from($message['type'])) {
            PublishableMessage::PullRequest => PublishablePullRequestMessage::from($message),
            PublishableMessage::CheckAnnotation => PublishableCheckAnnotationMessage::from(
                $message
            ),
            PublishableMessage::CheckRun => PublishableCheckRunMessage::from($message),
            PublishableMessage::Collection => PublishableMessageCollection::from($message),
            default => throw new InvalidArgumentException(sprintf("Invalid type provided: %s", $message['type'])),
        };
    }

    public static function tryFromMessageUsingType(array $message): PublishableMessageInterface|null
    {
        try {
            return self::fromMessageUsingType($message);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType()->value,
            'event' => $this->event->jsonSerialize(),
            'messages' => array_map(
                static fn(PublishableMessageInterface $message) => (array)$message->jsonSerialize(),
                $this->messages
            )
        ];
    }

    public function getMessageGroup(): string
    {
        return md5(
            implode('', [
                $this->event->getProvider()->value,
                $this->event->getOwner(),
                $this->event->getRepository(),
                $this->event->getPullRequest() ?: $this->event->getCommit()
            ])
        );
    }

    public function __toString(): string
    {
        return "PublishableMessageCollection#{$this->getValidUntil()->format(DateTimeInterface::ATOM)}";
    }

    public function count(): int
    {
        return count($this->messages);
    }
}
