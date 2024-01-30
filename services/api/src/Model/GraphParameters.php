<?php

namespace App\Model;

use Override;
use Packages\Contracts\Provider\Provider;

final class GraphParameters implements ParametersInterface
{
    public function __construct(
        private readonly string $owner,
        private readonly string $repository,
        private readonly Provider $provider,
    ) {
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
    public function getProvider(): Provider
    {
        return $this->provider;
    }
}
