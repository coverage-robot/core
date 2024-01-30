<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Packages\Contracts\Event\BaseAwareEventInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;

final class UploadsStarted implements EventInterface, BaseAwareEventInterface
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $ref,
        private readonly string $commit,
        private readonly string|int|null $pullRequest = null,
        private readonly ?string $baseRef = null,
        private readonly ?string $baseCommit = null,
        private ?DateTimeImmutable $eventTime = null
    ) {
        if (!$this->eventTime instanceof \DateTimeImmutable) {
            $this->eventTime = new DateTimeImmutable();
        }
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

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getPullRequest(): int|string|null
    {
        return $this->pullRequest;
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function getBaseRef(): ?string
    {
        return $this->baseRef;
    }

    public function getBaseCommit(): ?string
    {
        return $this->baseCommit;
    }

    public function getType(): Event
    {
        return Event::UPLOADS_STARTED;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->eventTime;
    }

    public function __toString(): string
    {
        return sprintf(
            'UploadsFinalised#%s-%s-%s-%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->ref,
            $this->commit,
            $this->pullRequest ?? ''
        );
    }
}