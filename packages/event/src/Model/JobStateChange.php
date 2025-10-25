<?php

declare(strict_types=1);

namespace Packages\Event\Model;

use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\BaseAwareEventInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\ParentAwareEventInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Enum\JobState;

final readonly class JobStateChange implements EventInterface, ParentAwareEventInterface, BaseAwareEventInterface
{
    /**
     * @param string[] $parent
     */
    public function __construct(
        private Provider $provider,
        private string $projectId,
        private string $owner,
        private string $repository,
        private string $ref,
        private string $commit,
        private array $parent,
        private string|int|null $externalId,
        private string|int|null $triggeredByExternalId,
        private JobState $state,
        private string|int|null $pullRequest = null,
        private ?string $baseCommit = null,
        private ?string $baseRef = null,
        private DateTimeImmutable $eventTime = new DateTimeImmutable()
    ) {
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
    public function getRef(): string
    {
        return $this->ref;
    }

    #[Override]
    public function getCommit(): string
    {
        return $this->commit;
    }

    /**
     * @return string[]
     */
    #[Override]
    public function getParent(): array
    {
        return $this->parent;
    }

    #[Override]
    public function getPullRequest(): string|int|null
    {
        return $this->pullRequest;
    }

    #[Override]
    public function getBaseCommit(): ?string
    {
        return $this->baseCommit;
    }

    #[Override]
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

    #[Override]
    public function getType(): Event
    {
        return Event::JOB_STATE_CHANGE;
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
            '%s#%s-%s-%s-%s-%s-%s',
            self::class,
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->ref,
            $this->commit,
            $this->pullRequest ?? ''
        );
    }
}
