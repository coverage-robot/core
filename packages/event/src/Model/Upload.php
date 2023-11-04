<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Event\Enum\Event;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Tag;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class Upload implements EventInterface
{
    #[Context(
        normalizationContext: [DateTimeNormalizer::FORMAT_KEY => DateTimeInterface::ATOM],
        denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => DateTimeInterface::ATOM],
    )]
    private readonly DateTimeImmutable $ingestTime;

    public function __construct(
        private readonly string $uploadId,
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $commit,
        private readonly array $parent,
        private readonly string $ref,
        private readonly string $projectRoot,
        private readonly string|int|null $pullRequest,
        private readonly Tag $tag,
        ?DateTimeInterface $ingestTime = null
    ) {
        if ($ingestTime) {
            $this->ingestTime = DateTimeImmutable::createFromInterface($ingestTime);
            return;
        }
        $this->ingestTime = new DateTimeImmutable();
    }

    public function getUploadId(): string
    {
        return $this->uploadId;
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

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    public function getPullRequest(): int|string|null
    {
        return $this->pullRequest;
    }

    public function getIngestTime(): DateTimeImmutable
    {
        return $this->ingestTime;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->ingestTime;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getParent(): array
    {
        return $this->parent;
    }

    public function getTag(): Tag
    {
        return $this->tag;
    }

    public function getType(): Event
    {
        return Event::UPLOAD;
    }

    public function __toString(): string
    {
        return 'Upload#' . $this->uploadId;
    }
}
