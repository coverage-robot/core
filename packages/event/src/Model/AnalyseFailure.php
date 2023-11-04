<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Models\Enum\Provider;

class AnalyseFailure implements EventInterface
{
    public function __construct(
        private readonly EventInterface $event
    ) {
    }

    public function getProvider(): Provider
    {
        return $this->event->getProvider();
    }

    public function getOwner(): string
    {
        return $this->event->getOwner();
    }

    public function getRepository(): string
    {
        return $this->event->getRepository();
    }

    public function getCommit(): string
    {
        return $this->event->getCommit();
    }

    public function getPullRequest(): int|string|null
    {
        return $this->event->getPullRequest();
    }

    public function getRef(): string
    {
        return $this->event->getRef();
    }

    public function getType(): Event
    {
        return Event::ANALYSE_FAILURE;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->event->getIngestTime();
    }

    public function __toString(): string
    {
        return sprintf(
            'AnalyseFailure#%s-%s-%s-%s-%s-%s',
            $this->getProvider()->value,
            $this->getOwner(),
            $this->getRepository(),
            $this->getRef(),
            $this->getCommit(),
            $this->getPullRequest() ?? ''
        );
    }
}
