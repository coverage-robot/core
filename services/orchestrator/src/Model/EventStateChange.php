<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;

final readonly class EventStateChange
{
    public function __construct(
        private Provider $provider,
        private string $identifier,
        private string $owner,
        private string $repository,
        private int $version,
        private array $event,
        private ?DateTimeImmutable $eventTime = null
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

    public function getEventTime(): ?DateTimeImmutable
    {
        return $this->eventTime;
    }
}
