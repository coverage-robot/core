<?php

namespace Packages\Models\Model\Event;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Models\Enum\Provider;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class PipelineStarted extends AbstractPipelineEvent
{
    public function __construct(
        Provider $provider,
        string $owner,
        string $repository,
        string $ref,
        string $commit,
        ?string $pullRequest,
        #[Context(
            normalizationContext: [DateTimeNormalizer::FORMAT_KEY => DateTimeInterface::ATOM],
            denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => DateTimeInterface::ATOM],
        )]
        private readonly DateTimeImmutable $startedAt
    ) {
        parent::__construct($provider, $owner, $repository, $ref, $commit, $pullRequest);
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->startedAt;
    }
}
