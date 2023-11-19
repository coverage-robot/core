<?php

namespace App\Model;

use App\Enum\OrchestratedEventState;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Serializer\Annotation\Ignore;

class Finalised extends AbstractOrchestratedEvent
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $ref,
        private readonly string $commit,
        private readonly int|string|null $pullRequest,
        private readonly DateTimeImmutable $eventTime,
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

    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getPullRequest(): int|string|null
    {
        return $this->pullRequest;
    }

    #[Ignore]
    public function getState(): OrchestratedEventState
    {
        return OrchestratedEventState::SUCCESS;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->eventTime;
    }

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
