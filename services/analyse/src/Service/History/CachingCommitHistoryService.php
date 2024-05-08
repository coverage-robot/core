<?php

namespace App\Service\History;

use App\Model\ReportWaypoint;
use App\Trait\InMemoryCacheTrait;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CachingCommitHistoryService implements CommitHistoryServiceInterface
{
    use InMemoryCacheTrait;

    private const string CACHE_METHOD_NAME = 'getPrecedingCommits';

    public function __construct(
        #[Autowire(service: CommitHistoryService::class)]
        private readonly CommitHistoryServiceInterface $commitHistoryService,
        private readonly LoggerInterface $commitHistoryLogger
    ) {
    }

    #[Override]
    public function getPrecedingCommits(ReportWaypoint $waypoint, int $page = 1): array
    {
        if (!$this->isCacheHit($waypoint, $page)) {
            /** @var array{commit: string, merged: bool, ref: string|null}[] $results */
            $results = $this->commitHistoryService->getPrecedingCommits($waypoint, $page);

            $this->persistInCache(
                $waypoint,
                $page,
                $results
            );
        }

        /**
         * @var array<int, array{commit: string, merged: bool, ref: string|null}[]> $results
         */
        $results = $this->getCacheValue(self::CACHE_METHOD_NAME, $waypoint, []);

        return $results[$page];
    }

    /**
     * Check if the cache contains a page for a given waypoint.
     */
    private function isCacheHit(ReportWaypoint $waypoint, int $page): bool
    {
        return isset($this->getCacheValue(self::CACHE_METHOD_NAME, $waypoint, [])[$page]);
    }

    /**
     * Persist a series of commits in the cache for a waypoints page.
     *
     * @param array{commit: string, merged: bool, ref: string|null}[] $commits
     */
    private function persistInCache(ReportWaypoint $waypoint, int $page, array $commits): void
    {
        /** @var array $pages */
        $pages = $this->getCacheValue(self::CACHE_METHOD_NAME, $waypoint, []);

        $this->setCacheValue(
            self::CACHE_METHOD_NAME,
            $waypoint,
            array_replace(
                $pages,
                [
                    $page => $commits
                ]
            )
        );
    }
}
