<?php

namespace Packages\Message\PublishableMessage;

use Countable;
use DateTimeInterface;
use Override;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class PublishableMessageCollection implements PublishableMessageInterface, Countable
{
    /**
     * @param PublishableMessageInterface[] $messages
     */
    public function __construct(
        private readonly EventInterface $event,
        #[Assert\NotBlank]
        #[Assert\All([
            new Assert\Type(type: PublishableMessageInterface::class)
        ])]
        private readonly array $messages,
    ) {
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    #[Override]
    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    #[Override]
    public function getValidUntil(): DateTimeInterface
    {
        return max(
            array_map(
                static fn(PublishableMessageInterface $message): DateTimeInterface => $message->getValidUntil(),
                $this->messages
            )
        );
    }

    #[Override]
    public function getType(): PublishableMessage
    {
        return PublishableMessage::COLLECTION;
    }

    #[Override]
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

    #[Override]
    public function __toString(): string
    {
        return 'PublishableMessageCollection#' . $this->getValidUntil()->format(DateTimeInterface::ATOM);
    }

    #[Override]
    public function count(): int
    {
        return count($this->messages);
    }
}
