<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Enum\JobState;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class JobStateChange implements EventInterface
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
        private readonly string|int|null $pullRequest,
        private readonly string|null $baseCommit,
        private readonly string|null $baseRef,
        private readonly string|int $externalId,
        private readonly JobState $state,
        #[Context(
            normalizationContext: [DateTimeNormalizer::FORMAT_KEY => DateTimeInterface::ATOM],
            denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => DateTimeInterface::ATOM],
        )]
        private readonly DateTimeImmutable $eventTime
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

    public function getExternalId(): string|int
    {
        return $this->externalId;
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
