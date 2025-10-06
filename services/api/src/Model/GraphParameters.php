<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Packages\Contracts\Provider\Provider;

final readonly class GraphParameters implements ParametersInterface
{
    public function __construct(
        private string $owner,
        private string $repository,
        private Provider $provider,
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
