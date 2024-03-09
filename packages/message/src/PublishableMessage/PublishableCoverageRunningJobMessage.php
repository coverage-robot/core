<?php

namespace Packages\Message\PublishableMessage;

use DateTimeImmutable;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Event\Model\EventInterface;

final class PublishableCoverageRunningJobMessage implements PublishableCheckRunMessageInterface
{
    public function __construct(
        private readonly EventInterface $event,
        private ?DateTimeImmutable $validUntil = null,
    ) {
        if (!$this->validUntil instanceof DateTimeImmutable) {
            $this->validUntil = new DateTimeImmutable();
        }
    }

    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    public function getValidUntil(): DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function getStatus(): PublishableCheckRunStatus
    {
        return PublishableCheckRunStatus::IN_PROGRESS;
    }

    public function getType(): PublishableMessage
    {
        return PublishableMessage::COVERAGE_RUNNING_JOB;
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
            "PublishableCoverageRunningJobMessage#%s-%s-%s",
            $this->event->getOwner(),
            $this->event->getRepository(),
            $this->event->getCommit()
        );
    }
}
