<?php

namespace Packages\Message\PublishableMessage;

use Countable;
use DateTimeInterface;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class PublishableMessageCollection implements PublishableMessageInterface, Countable
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
        #[Assert\NotBlank]
        #[Assert\All([
            new Assert\Type(type: PublishableMessageInterface::class)
        ])]
        array $messages,
    ) {
        $this->messages = array_filter(
            $messages,
            static fn(mixed $message): true => $message instanceof PublishableMessageInterface
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
                static fn(PublishableMessageInterface $message): \DateTimeInterface => $message->getValidUntil(),
                $this->messages
            )
        );
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::COLLECTION;
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
        return 'PublishableMessageCollection#' . $this->getValidUntil()->format(DateTimeInterface::ATOM);
    }

    public function count(): int
    {
        return count($this->messages);
    }
}
