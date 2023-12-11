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
        private readonly ?PublishableCheckRunStatus $status,
        private readonly array $annotations,
        private readonly float $coveragePercentage,
        private readonly DateTimeImmutable $validUntil,
    ) {
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
