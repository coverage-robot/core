<?php

namespace App\Model;

use App\Enum\ProviderEnum;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

class Upload implements JsonSerializable
{
    private readonly DateTimeImmutable $ingestTime;
    private readonly ProviderEnum $provider;

    public function __construct(
        private readonly Project $project,
        private readonly string $uploadId,
        string $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $commit,
        private readonly array $parent,
        private readonly string $ref,
        private readonly string|int|null $pullRequest,
        private readonly string $tag,
        ?DateTimeInterface $ingestTime = null
    ) {
        $this->provider = ProviderEnum::from($provider);

        if ($ingestTime) {
            $this->ingestTime = DateTimeImmutable::createFromInterface($ingestTime);
            return;
        }
        $this->ingestTime = new DateTimeImmutable();
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getUploadId(): string
    {
        return $this->uploadId;
    }

    public function getProvider(): ProviderEnum
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

    public function getPullRequest(): int|string|null
    {
        return $this->pullRequest;
    }

    public function getIngestTime(): DateTimeImmutable
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

    public function getTag(): string
    {
        return $this->tag;
    }

    public function __toString(): string
    {
        return 'Upload#' . $this->uploadId;
    }

    public function jsonSerialize(): array
    {
        $fields = [
            'uploadId' => $this->uploadId,
            'provider' => $this->provider->value,
            'owner' => $this->owner,
            'repository' => $this->repository,
            'ingestTime' => $this->ingestTime->format(DateTimeInterface::ATOM),
            'commit' => $this->commit,
            'parent' => $this->parent,
            'ref' => $this->ref,
            'tag' => $this->tag
        ];

        if ($this->pullRequest) {
            $fields['pullRequest'] = $this->pullRequest;
        }

        return $fields;
    }
}
