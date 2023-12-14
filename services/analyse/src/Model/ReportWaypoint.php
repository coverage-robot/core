<?php

namespace App\Model;

use Packages\Contracts\Provider\Provider;
use Stringable;

class ReportWaypoint implements Stringable
{
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $ref,
        private readonly string $commit,
    ) {
    }

    public function getProvider(): Provider
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

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function comparable(ReportWaypoint $other): bool
    {
        return $this->provider === $other->provider
            && $this->owner === $other->owner
            && $this->repository === $other->repository
            && $this->ref === $other->ref;
    }

    public function __toString(): string
    {
        return sprintf(
            'ReportWaypoint#%s-%s-%s-%s-%s',
            $this->provider->value,
            $this->owner,
            $this->repository,
            $this->ref,
            $this->commit
        );
    }
}
