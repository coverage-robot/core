<?php

namespace Packages\Models\Model\PublishableMessage;

use Countable;
use DateTimeInterface;
use Packages\Event\Model\EventInterface;

class PublishableMessageCollection implements PublishableMessageInterface, Countable
{
    /**
     * @var PublishableMessageInterface[] $messages
     */
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
