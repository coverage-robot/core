<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;

class ConfigurationFileChange implements EventInterface
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $ref,
        private readonly string $commit,
        private readonly ?DateTimeImmutable $eventTime = null
    ) {
        if ($this->eventTime === null) {
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
    public function getPullRequest(): int|string|null
    {
        return null;
    }

    #[Override]
    public function getBaseRef(): ?string
    {
        return null;
    }

    #[Override]
    public function getBaseCommit(): ?string
    {
        return null;
    }

    #[Override]
    public function getEventTime(): DateTimeImmutable
    {
        return $this->eventTime;
    }

    #[Override]
    public function getType(): Event
    {
        return Event::CONFIGURATION_FILE_CHANGE;
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf(
            'ConfigurationFileChange#%s-%s-%s-%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->ref,
            $this->commit,
            $this->pullRequest ?? ''
        );
    }
}
