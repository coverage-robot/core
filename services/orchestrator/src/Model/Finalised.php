<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\OrchestratedEventState;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Provider\Provider;

final class Finalised extends AbstractOrchestratedEvent
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $projectId,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $ref,
        private readonly string $commit,
        private readonly OrchestratedEventState $state,
        private readonly int|string|null $pullRequest,
        private readonly DateTimeImmutable $eventTime,
    ) {
        parent::__construct($provider, $owner, $repository);
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
    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    #[Override]
    public function getCommit(): string
    {
        return $this->commit;
    }

    #[Override]
    public function getState(): OrchestratedEventState
    {
        return $this->state;
    }

    public function getPullRequest(): int|string|null
    {
        return $this->pullRequest;
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
            'Finalised#%s-%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->commit
        );
    }
}
