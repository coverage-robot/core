<?php

namespace Packages\Models\Model\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Event\Model\EventInterface;
use Packages\Models\Enum\PublishableCheckRunStatus;

class PublishableCheckRunMessage implements PublishableMessageInterface
{
    /**
     * @var PublishableCheckAnnotationMessage[]
     */
    private array $annotations;

    /**
     * @param PublishableCheckAnnotationMessage[] $annotations
     */
    public function __construct(
        private readonly EventInterface $event,
        private readonly ?PublishableCheckRunStatus $status,
        array $annotations,
        private readonly float $coveragePercentage,
        private readonly DateTimeImmutable $validUntil,
    ) {
        $this->annotations = array_filter(
            $annotations,
            static fn(mixed $annotation) => $annotation instanceof PublishableCheckAnnotationMessage
        );
    }

    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    public function getStatus(): ?PublishableCheckRunStatus
    {
        return $this->status;
    }

    /**
     * @return PublishableCheckAnnotationMessage[]
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    public function getValidUntil(): DateTimeInterface
    {
        return $this->validUntil;
    }

    public function getCoveragePercentage(): float
    {
        return $this->coveragePercentage;
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
            "PublishableCheckRunMessage#%s-%s-%s",
            $this->event->getOwner(),
            $this->event->getRepository(),
            $this->event->getCommit()
        );
    }
}
