<?php

namespace Packages\Models\Model\Event;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Models\Enum\JobState;
use Packages\Models\Enum\Provider;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class JobStateChange implements EventInterface
{
    /**
     * @param array-key|int $index
     */
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $ref,
        private readonly string $commit,
        private readonly string|int|null $pullRequest,
        private readonly string|int $externalId,
        private readonly int $index,
        private readonly JobState $state,
        private readonly JobState $suiteState,
        private readonly bool $initialState,
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

    public function getPullRequest(): ?string
    {
        return $this->pullRequest;
    }

    public function getExternalId(): string|int
    {
        return $this->externalId;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getState(): JobState
    {
        return $this->state;
    }

    public function getSuiteState(): JobState
    {
        return $this->suiteState;
    }

    public function isInitialState(): bool
    {
        return $this->initialState;
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
