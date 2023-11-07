<?php

namespace App\Model;

use Packages\Models\Enum\Provider;

class EventStateChange
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $identifier,
        private readonly string $owner,
        private readonly string $repository,
        private readonly int $version,
        private readonly array $event,
        private readonly int $expiry
    ) {
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getEvent(): array
    {
        return $this->event;
    }

    public function getExpiry(): int
    {
        return $this->expiry;
    }
}
