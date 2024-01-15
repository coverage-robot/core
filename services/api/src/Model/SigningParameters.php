<?php

namespace App\Model;

use Packages\Contracts\Provider\Provider;
use Symfony\Component\Validator\Constraints as Assert;

class SigningParameters implements ParametersInterface
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[\\w\-\.]+$/i')]
        private readonly string $owner,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[\\w\-\.]+$/i')]
        private readonly string $repository,
        private readonly Provider $provider,
        #[Assert\NotBlank]
        private readonly string $fileName,
        private readonly string $projectRoot,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-zA-Z0-9\.\-_]+$/')]
        private readonly string $tag,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-f0-9]{40}$/')]
        private readonly string $commit,
        #[Assert\All([
            new Assert\NotBlank(),
            new Assert\Type('string'),
            new Assert\Regex(pattern: '/^[a-f0-9]{40}$/')
        ])]
        private readonly array $parent,
        #[Assert\NotBlank]
        private readonly string $ref,
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Regex(pattern: '/^\d+$/')]
        private readonly string|int|null $pullRequest,
        #[Assert\NotBlank(allowNull: true)]
        private readonly ?string $baseRef,
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Regex(pattern: '/^[a-f0-9]{40}$/')]
        private readonly ?string $baseCommit
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
