<?php

declare(strict_types=1);

namespace App\Model;

use Packages\Contracts\Provider\Provider;

final class Project
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $projectId,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $email,
        private readonly string $graphToken,
    ) {
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function getProjectId(): string
    {
        return $this->projectId;
    }

    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getGraphToken(): string
    {
        return $this->graphToken;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
