<?php

declare(strict_types=1);

namespace Packages\Event\Model;

use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\BaseAwareEventInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;

final class CoverageFailed implements EventInterface, BaseAwareEventInterface
{
    private DateTimeImmutable $eventTime;

    public function __construct(
        private readonly Provider $provider,
        private readonly string $projectId,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $ref,
        private readonly string $commit,
        private readonly string|int|null $pullRequest = null,
        private readonly ?string $baseRef = null,
        private readonly ?string $baseCommit = null,
        ?DateTimeImmutable $eventTime = null
    ) {
        $this->eventTime = $eventTime ?? new DateTimeImmutable();
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

    #[Override]
    public function getCommit(): string
    {
        return $this->commit;
    }

    #[Override]
    public function getPullRequest(): int|string|null
    {
        return $this->pullRequest;
    }

    #[Override]
    public function getRef(): string
    {
        return $this->ref;
    }

    #[Override]
    public function getBaseRef(): ?string
    {
        return $this->baseRef;
    }

    #[Override]
    public function getBaseCommit(): ?string
    {
        return $this->baseCommit;
    }

    #[Override]
    public function getType(): Event
    {
        return Event::COVERAGE_FAILED;
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
            'CoverageFailed#%s-%s-%s-%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->ref,
            $this->commit,
            $this->pullRequest ?? ''
        );
    }
}