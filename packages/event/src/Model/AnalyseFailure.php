<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Models\Enum\Provider;
use Symfony\Component\Serializer\Annotation\Ignore;

class AnalyseFailure implements EventInterface
{
    public function __construct(
        private readonly EventInterface $event
    ) {
    }

    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    #[Ignore]
    public function getProvider(): Provider
    {
        return $this->event->getProvider();
    }

    #[Ignore]
    public function getOwner(): string
    {
        return $this->event->getOwner();
    }

    #[Ignore]
    public function getRepository(): string
    {
        return $this->event->getRepository();
    }

    #[Ignore]
    public function getCommit(): string
    {
        return $this->event->getCommit();
    }

    #[Ignore]
    public function getPullRequest(): int|string|null
    {
        return $this->event->getPullRequest();
    }

    #[Ignore]
    public function getRef(): string
    {
        return $this->event->getRef();
    }

    #[Ignore]
    public function getType(): Event
    {
        return Event::ANALYSE_FAILURE;
    }

    #[Ignore]
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
