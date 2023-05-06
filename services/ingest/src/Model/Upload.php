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

    /**
     * @param Project $project
     * @param string $uploadId
     * @param string $provider
     * @param string $owner
     * @param string $repository
     * @param string $commit
     * @param string $parent
     * @param int $pullRequest
     * @param DateTimeInterface|null $ingestTime
     */
    public function __construct(
        private readonly Project $project,
        private readonly string $uploadId,
        string $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $commit,
        private readonly string $parent,
        private readonly int $pullRequest,
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

    public function getPullRequest(): int
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

    public function getParent(): string
    {
        return $this->parent;
    }

    public function __toString(): string
    {
        return "Upload #" . $this->uploadId;
    }

    public function jsonSerialize(): array
    {
        return [
            'uploadId' => $this->uploadId,
            'provider' => $this->provider->value,
            'owner' => $this->owner,
            'repository' => $this->repository,
            'ingestTime' => $this->ingestTime->format(DATE_ATOM),
            'commit' => $this->commit,
            'parent' => $this->parent,
            'pullRequest' => $this->pullRequest
        ];
    }
}
