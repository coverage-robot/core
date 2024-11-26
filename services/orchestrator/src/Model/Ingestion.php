<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\OrchestratedEventState;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Provider\Provider;

final class Ingestion extends AbstractOrchestratedEvent
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $projectId,
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
    public function getProjectId(): string
    {
        return $this->projectId;
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
            'Ingestion#%s-%s',
            $this->projectId,
            $this->uploadId
        );
    }
}
