<?php

namespace App\Model;

use Packages\Contracts\Provider\Provider;

class SigningParameters implements ParametersInterface
{
    public function __construct(
        private readonly string $owner,
        private readonly string $repository,
        private readonly Provider $provider,
        private readonly string $fileName,
        private readonly string $projectRoot,
        private readonly string $tag,
        private readonly string $commit,
        private readonly array $parent,
        private readonly string $ref,
        private readonly string|int|null $pullRequest,
        private readonly string|null $baseRef,
        private readonly string|null $baseCommit
    ) {
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getParent(): array
    {
        return $this->parent;
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function getPullRequest(): string|int|null
    {
        return $this->pullRequest;
    }

    public function getBaseRef(): ?string
    {
        return $this->baseRef;
    }

    public function getBaseCommit(): ?string
    {
        return $this->baseCommit;
    }
}
