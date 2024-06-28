<?php

namespace Packages\Event\Model;

use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\BaseAwareEventInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\ParentAwareEventInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Symfony\Component\Validator\Constraints as Assert;

final class Upload implements EventInterface, ParentAwareEventInterface, BaseAwareEventInterface
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
        private readonly ?string $baseCommit = null,
        private readonly ?string $baseRef = null,
        private ?DateTimeImmutable $eventTime = null
    ) {
        if (!$this->eventTime instanceof \DateTimeImmutable) {
            $this->eventTime = new DateTimeImmutable();
        }
    }

    #[Assert\NotBlank]
    #[Assert\Uuid(versions: [Assert\Uuid::V7_MONOTONIC])]
    public function getUploadId(): string
    {
        return $this->uploadId;
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

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    #[Override]
    public function getPullRequest(): int|string|null
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

    #[Override]
    public function getEventTime(): DateTimeImmutable
    {
        return $this->eventTime;
    }

    #[Override]
    public function getCommit(): string
    {
        return $this->commit;
    }

    #[Override]
    public function getParent(): array
    {
        return $this->parent;
    }

    public function getTag(): Tag
    {
        return $this->tag;
    }

    #[Override]
    public function getType(): Event
    {
        return Event::UPLOAD;
    }

    #[Override]
    public function __toString(): string
    {
        return 'Upload#' . $this->uploadId;
    }
}
