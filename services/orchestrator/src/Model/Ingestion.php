<?php

namespace App\Model;

use App\Enum\OrchestratedEventState;
use Packages\Models\Enum\Provider;

class Ingestion extends AbstractOrchestratedEvent
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $commit,
        private readonly string $uploadId,
        private readonly OrchestratedEventState $state
    ) {
        parent::__construct($provider, $owner, $repository);
    }

    public function getUploadId(): string
    {
        return $this->uploadId;
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
