<?php

namespace Packages\Models\Model\Event;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Models\Enum\Provider;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class PipelineComplete extends AbstractPipelineEvent
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
        private readonly DateTimeImmutable $completedAt
    ) {
        parent::__construct($provider, $owner, $repository, $ref, $commit, $pullRequest);
    }

    public function getCompletedAt(): DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->completedAt;
    }
}
