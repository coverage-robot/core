<?php

namespace App\Model;

use App\Enum\OrchestratedEventState;
use Packages\Models\Enum\Provider;

class Ingestion implements OrchestratedEventInterface
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $commit,
        private readonly OrchestratedEventState $state
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
            $this->commit
        );
    }
}
