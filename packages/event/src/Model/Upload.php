<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use DateTimeInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Models\Model\Tag;

class Upload implements EventInterface
{
    /**
     * @param string[] $parent
     */
    public function __construct(
        private readonly string $uploadId,
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $commit,
        private readonly array $parent,
        private readonly string $ref,
        private readonly string $projectRoot,
        private readonly Tag $tag,
        private readonly string|int|null $pullRequest = null,
        private readonly string|null $baseCommit = null,
        private readonly string|null $baseRef = null,
        private ?DateTimeInterface $eventTime = null
    ) {
        if ($this->eventTime === null) {
            $this->eventTime = new DateTimeImmutable();
        }
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

    public function getBaseCommit(): ?string
    {
        return $this->baseCommit;
    }

    public function getBaseRef(): ?string
    {
        return $this->baseRef;
    }

    public function getEventTime(): DateTimeImmutable
    {
        return $this->eventTime;
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
