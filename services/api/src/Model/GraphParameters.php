<?php

namespace App\Model;

use Packages\Models\Enum\Provider;

class GraphParameters implements ParametersInterface
{
    public function __construct(
        private readonly string $owner,
        private readonly string $repository,
        private readonly Provider $provider,
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
}
