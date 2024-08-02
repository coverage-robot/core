<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Validator\Constraints as Assert;

final class UtilisationAmendment implements EventInterface
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $ref,
        private readonly string $commit,
        private readonly int $analysedCoverage,
        private readonly string|int|null $pullRequest = null,
        private ?DateTimeImmutable $eventTime = null,
    ) {
        if (!$this->eventTime instanceof \DateTimeImmutable) {
            $this->eventTime = new DateTimeImmutable();
        }
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

    #[Assert\NotIdenticalTo(value: 0)]
    public function getAnalysedCoverage(): int
    {
        return $this->analysedCoverage;
    }

    #[Override]
    public function getType(): Event
    {
        return Event::UTILISATION_AMENDMENT;
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
            'QuotaUtilisationAdjustment#%s-%s-%s-%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->ref,
            $this->commit,
            $this->pullRequest ?? ''
        );
    }
}
