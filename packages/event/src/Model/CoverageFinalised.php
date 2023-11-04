<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Models\Enum\Provider;

class CoverageFinalised implements EventInterface
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $ref,
        private readonly string $commit,
        private readonly string|int|null $pullRequest,
        private readonly float $coveragePercentage,
        private readonly DateTimeImmutable $eventTime
    ) {
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getPullRequest(): int|string|null
    {
        return $this->pullRequest;
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function getCoveragePercentage(): float
    {
        return $this->coveragePercentage;
    }

    public function getType(): Event
    {
        return Event::COVERAGE_FINALISED;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->eventTime;
    }

    public function __toString(): string
    {
        return sprintf(
            'CoverageFinalised#%s-%s-%s-%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->ref,
            $this->commit,
            $this->pullRequest ?? ''
        );
    }
}
