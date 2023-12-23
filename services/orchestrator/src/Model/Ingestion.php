<?php

namespace App\Model;

use App\Enum\OrchestratedEventState;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Provider\Provider;

class Ingestion extends AbstractOrchestratedEvent
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $commit,
        private readonly string $uploadId,
        private readonly OrchestratedEventState $state,
        private readonly DateTimeImmutable $eventTime,
    ) {
        parent::__construct($provider, $owner, $repository);
    }

    public function getUploadId(): string
    {
        return $this->uploadId;
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

    #[Override]
    public function __toString(): string
    {
        return sprintf(
            'Ingestion#%s-%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->uploadId
        );
    }
}
