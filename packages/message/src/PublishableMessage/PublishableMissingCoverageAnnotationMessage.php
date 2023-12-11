<?php

namespace Packages\Message\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;

class PublishableMissingCoverageAnnotationMessage implements PublishableAnnotationInterface, PublishableMessageInterface
{
    public function __construct(
        private readonly EventInterface $event,
        private readonly string $fileName,
        private readonly bool $isStartingOnMethod,
        private readonly int $startLineNumber,
        private readonly int $endLineNumber,
        private readonly DateTimeImmutable $validUntil
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

    /**
     * Whether or not this annotation is starting on a method.
     *
     * This helps us to add contextual information to the annotation, such as changing
     * wording.
     */
    public function isStartingOnMethod(): bool
    {
        return $this->isStartingOnMethod;
    }

    public function getStartLineNumber(): int
    {
        return $this->startLineNumber;
    }

    public function getEndLineNumber(): int
    {
        return $this->endLineNumber;
    }

    public function getValidUntil(): DateTimeInterface
    {
        return $this->validUntil;
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::MISSING_COVERAGE_ANNOTATION;
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
