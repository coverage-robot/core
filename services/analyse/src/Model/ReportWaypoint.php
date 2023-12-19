<?php

namespace App\Model;

use Closure;
use Packages\Contracts\Provider\Provider;
use Stringable;

class ReportWaypoint implements Stringable
{
    /**
     *  @param Closure(ReportWaypoint $waypoint, int $page):array{
     *      commit: string,
     *      isOnBaseRef: bool
     *  }[]|array{
     *      commit: string,
     *      isOnBaseRef: bool
     *  }[] $history
     *  @param Closure(ReportWaypoint $waypoint):array<string, array<int, int>>|array<string, array<int, int>> $diff
     */
    public function __construct(
        private readonly Provider $provider,
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $ref,
        private readonly string $commit,
        private readonly string|int|null $pullRequest,
        private readonly Closure|array $history,
        private Closure|array $diff
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

    public function getPullRequest(): string|int|null
    {
        return $this->pullRequest;
    }

    /**
     * @return array{
     *     commit: string,
     *     isOnBaseRef: bool
     * }[]
     */
    public function getHistory(int $page = 1): array
    {
        if (!is_callable($this->history)) {
            return $this->history;
        }

        // Don't lazy load a single value here as the history can be paginated through
        // on demand
        return ($this->history)($this, $page);
    }

    /**
     * @return array<string, array<int, int>>
     */
    public function getDiff(): array
    {
        if (is_callable($this->diff)) {
            $this->diff = ($this->diff)($this);
        }

        return $this->diff;
    }

    public function comparable(ReportWaypoint $other): bool
    {
        return $this->provider === $other->provider
            && $this->owner === $other->owner
            && $this->repository === $other->repository;
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
