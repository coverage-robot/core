<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Packages\Contracts\Event\BaseAwareEventInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\ParentAwareEventInterface;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Serializer\Annotation\Ignore;

class AnalyseFailure implements EventInterface, ParentAwareEventInterface, BaseAwareEventInterface
{
    public function __construct(
        private readonly EventInterface $event,
        private readonly DateTimeImmutable $eventTime
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
    public function getParent(): array
    {
        return $this->event->getParent();
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
    public function getBaseRef(): ?string
    {
        return $this->event->getBaseRef();
    }

    #[Ignore]
    public function getBaseCommit(): ?string
    {
        return $this->event->getBaseCommit();
    }

    public function getType(): Event
    {
        return Event::ANALYSE_FAILURE;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->eventTime;
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
