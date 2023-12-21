<?php

namespace Packages\Message\PublishableMessage;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;

class PublishableCheckRunMessage implements PublishableMessageInterface
{
    /**
     * @param PublishableAnnotationInterface[] $annotations
     */
    public function __construct(
        private readonly EventInterface $event,
        private readonly PublishableCheckRunStatus $status,
        private readonly float $coveragePercentage,
        private readonly array $annotations = [],
        private readonly ?string $baseCommit = null,
        private readonly ?float $coverageChange = 0,
        private ?DateTimeImmutable $validUntil = null,
    ) {
        if ($this->validUntil === null) {
            $this->validUntil = new DateTimeImmutable();
        }
    }

    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    public function getStatus(): PublishableCheckRunStatus
    {
        return $this->status;
    }

    /**
     * @return PublishableAnnotationInterface[]
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

    public function getBaseCommit(): ?string
    {
        return $this->baseCommit;
    }

    public function getCoverageChange(): ?float
    {
        return $this->coverageChange;
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::CHECK_RUN;
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
