<?php

namespace App\Model;

use App\Enum\OrchestratedEventState;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Provider\Provider;

final class Job extends AbstractOrchestratedEvent
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $commit,
        private readonly OrchestratedEventState $state,
        private readonly DateTimeImmutable $eventTime,
        private readonly string|int|null $externalId
    ) {
        parent::__construct($provider, $owner, $repository);
    }

    #[Override]
    public function getProvider(): Provider
    {
        return $this->provider;
    }

    #[Override]
    public function getOwner(): string
    {
        return $this->owner;
    }

    #[Override]
    public function getCommit(): string
    {
        return $this->commit;
    }

    #[Override]
    public function getRepository(): string
    {
        return $this->repository;
    }

    #[Override]
    public function getState(): OrchestratedEventState
    {
        return $this->state;
    }

    #[Override]
    public function getEventTime(): DateTimeImmutable
    {
        return $this->eventTime;
    }

    public function getExternalId(): string|int|null
    {
        return $this->externalId;
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf(
            'Job#%s-%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository,
            (string) $this->externalId
        );
    }
}
