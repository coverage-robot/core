<?php

namespace Packages\Message\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;
use Packages\Models\Enum\LineState;

class PublishableCheckAnnotationMessage implements PublishableMessageInterface
{
    public function __construct(
        private readonly EventInterface $event,
        private readonly string $fileName,
        private readonly int $lineNumber,
        private readonly LineState $lineState,
        private readonly DateTimeImmutable $validUntil,
    ) {
    }

    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getLineState(): LineState
    {
        return $this->lineState;
    }

    public function getValidUntil(): DateTimeInterface
    {
        return $this->validUntil;
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::CheckAnnotation;
    }

    public function getMessageGroup(): string
    {
        return md5(
            implode('', [
                $this->event->getProvider()->value,
                $this->event->getOwner(),
                $this->event->getRepository(),
                $this->event->getCommit()
            ])
        );
    }

    public function __toString(): string
    {
        return sprintf(
            "PublishableCheckAnnotationMessage#%s-%s-%s",
            $this->event->getOwner(),
            $this->event->getRepository(),
            $this->event->getCommit()
        );
    }
}
