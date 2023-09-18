<?php

namespace Packages\Models\Model\Event;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Models\Enum\EventType;
use Packages\Models\Enum\Provider;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class PipelineComplete implements EventInterface
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $ref,
        private readonly string $commit,
        private readonly ?string $pullRequest,
        #[Context(
            normalizationContext: [DateTimeNormalizer::FORMAT_KEY => DateTimeInterface::ATOM],
            denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => DateTimeInterface::ATOM],
        )]
        private readonly DateTimeImmutable $completedAt
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

    public function getCompletedAt(): DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getEventType(): EventType
    {
        return EventType::PIPELINE_COMPLETE;
    }

    public function __toString(): string
    {
        return sprintf(
            'PipelineComplete#%s-%s-%s-%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->ref,
            $this->commit,
            $this->pullRequest
        );
    }
}
