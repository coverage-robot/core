<?php

namespace App\Model;

use App\Enum\OrchestratedEventState;
use DateTimeImmutable;
use Packages\Models\Enum\Provider;

class Job extends AbstractOrchestratedEvent
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $commit,
        private readonly OrchestratedEventState $state,
        private readonly DateTimeImmutable $eventTime,
        private readonly string|int $externalId
    ) {
        parent::__construct($provider, $owner, $repository);
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getState(): OrchestratedEventState
    {
        return $this->state;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->eventTime;
    }

    public function getExternalId(): string|int
    {
        return $this->externalId;
    }

    public function __toString(): string
    {
        return sprintf(
            'Job#%s-%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->externalId
        );
    }
}
