<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Packages\Contracts\Event\BaseAwareEventInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\ParentAwareEventInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Enum\JobState;

final class JobStateChange implements EventInterface, ParentAwareEventInterface, BaseAwareEventInterface
{
    /**
     * @param string[] $parent
     */
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $ref,
        private readonly string $commit,
        private readonly array $parent,
        private readonly string|int|null $externalId,
        private readonly string|int|null $triggeredByExternalId,
        private readonly JobState $state,
        private readonly string|int|null $pullRequest = null,
        private readonly ?string $baseCommit = null,
        private readonly ?string $baseRef = null,
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

    public function getRef(): string
    {
        return $this->ref;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    /**
     * @return string[]
     */
    public function getParent(): array
    {
        return $this->parent;
    }

    public function getPullRequest(): ?string
    {
        return $this->pullRequest;
    }

    public function getBaseCommit(): ?string
    {
        return $this->baseCommit;
    }

    public function getBaseRef(): ?string
    {
        return $this->baseRef;
    }

    /**
     * The ID of the entity in the provider which the state change is for.
     *
     * For example, this may be the check run ID from GitHub.
     */
    public function getExternalId(): string|int|null
    {
        return $this->externalId;
    }

    /**
     * The ID of the person (app, etc) in the provider which triggered the state change.
     *
     * For example, this may be the app ID from GitHub.
     */
    public function getTriggeredByExternalId(): int|string|null
    {
        return $this->triggeredByExternalId;
    }

    public function getState(): JobState
    {
        return $this->state;
    }

    public function getType(): Event
    {
        return Event::JOB_STATE_CHANGE;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->eventTime;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s#%s-%s-%s-%s-%s-%s',
            get_class($this),
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->ref,
            $this->commit,
            $this->pullRequest ?? ''
        );
    }
}
