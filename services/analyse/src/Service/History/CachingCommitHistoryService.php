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
            // We've not got the fully populated page yet, so see if we can populate
            // the history from commits in the cache for other similar waypoints.
            $this->tryPopulatingCacheFromComparableWaypoints($waypoint, $page);
        }

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
     * This method allows our commit history service to be a bit smarter with how
     * the in memory cache makes use of the history we've already seen.
     *
     * Effectively, it uses comparable waypoints which have already been cached (i.e. ones that
     * exist in the same owner/repository context) to try and populate the cache for the current
     * waypoint being called.
     *
     * Large this is a best effort behaviour, as we can't guarantee that the waypoints will always be
     * available. However it does allow us to save some computation and request overhead.
     *
     * We can do this because of the causal nature of git history, i.e. if a commit is in the history,
     * we're confident the commits before it will be the same regardless of which point in the tree
     * we've entered at.
     *
     * For example, consider a two comparable waypoints called sequentially
     * ```
     * commit-1 <- First waypoint starts here (and is cached)
     * commit-2
     * commit-3 <- A different waypoint starts here (and is not cached)
     * ...
     * commit-99
     * ```
     * In this case we can use the fist waypoints history to populate the cache for the second waypoint
     * because we know that the commits preceding commit-3 will be the same as the commits preceding
     * commit-1.
     */
    private function tryPopulatingCacheFromComparableWaypoints(ReportWaypoint $waypoint, int $page): void
    {
        /**
         * @var ReportWaypoint $cachedWaypoint
         * @var array<int, array{commit: string, merged: bool, ref: string|null}[]> $history
         */
        foreach ($this->getAllCacheValues(self::CACHE_METHOD_NAME) as $cachedWaypoint => $history) {
            if ($cachedWaypoint === $waypoint) {
                // We don't want to compare the waypoint to itself
                continue;
            }

            if (!$cachedWaypoint->comparable($waypoint)) {
                // We don't want to compare the waypoint if its not comparable
                continue;
            }

            foreach ($history as $cachedPage => $cachedCommits) {
                $commitIndex = array_search(
                    $waypoint->getCommit(),
                    array_column($cachedCommits, 'commit'),
                    true
                );

                if ($commitIndex === false) {
                    // This waypoint doesn't contain the commit we're looking
                    // for, so we've not yet seen it in the cache
                    continue;
                }

                // We want to offset so that we don't include the waypoint as the first historic
                // commit
                ++$commitIndex;
                $pageOffset = $page - 1;

                if ($commitIndex >= (count($cachedCommits) - 1)) {
                    // If the index is past the whole page, we want to move the index
                    // back to 0 and start from the next page
                    $commitIndex = 0;
                    ++$pageOffset;
                }

                if ($pageOffset > 0) {
                    if (!$this->isCacheHit($cachedWaypoint, $cachedPage + $pageOffset)) {
                        // The page we want is not yet populated. We could populate it, but that would _increase_
                        // the overhead (because we'd have to make 2 uncached calls to populate the cache). Instead,
                        // we'll just skip and allow the page to be fetched directly (1 uncached call)
                        break;
                    }

                    // We've populated the page we're looking for, so we can switch to that page
                    // with no additional overhead
                    $cachedPage += $pageOffset;
                    $cachedCommits = $this->getPrecedingCommits($cachedWaypoint, $cachedPage);
                }

                $usableCachedCommits = array_slice(
                    $cachedCommits,
                    $commitIndex,
                    CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE
                );

                if ($this->isPageFullyPopulated($usableCachedCommits)) {
                    $this->commitHistoryLogger->info(
                        sprintf(
                            'Populated cache for %s using %s (no requests required)',
                            (string)$waypoint,
                            (string)$cachedWaypoint
                        ),
                        [
                            'cachedCommits' => $usableCachedCommits,
                            'usableCachedCommits' => $usableCachedCommits,
                        ]
                    );

                    // Our slice of the existing history is enough to fill the whole
                    // page, so we're okay to populate the cache and move on
                    $this->persistInCache($waypoint, $page, $usableCachedCommits);
                    break;
                }

                if ($this->isPageFullyPopulated($cachedCommits)) {
                    // The page we're looking for is fully populated, meaning there may be
                    // more commits in the history we've not yet seen.
                    $usableCachedCommits = [
                        ...$usableCachedCommits,
                        ...array_slice(
                            $this->getPrecedingCommits($cachedWaypoint, $cachedPage + 1),
                            0,
                            CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - count($usableCachedCommits)
                        )
                    ];

                    $this->commitHistoryLogger->info(
                        sprintf(
                            'Populated cache for %s using %s (1 request required)',
                            (string)$waypoint,
                            (string)$cachedWaypoint
                        ),
                        [
                            'cachedCommits' => $usableCachedCommits,
                            'usableCachedCommits' => $usableCachedCommits,
                        ]
                    );
                    $this->persistInCache($waypoint, $page, $usableCachedCommits);
                    break;
                }

                $this->commitHistoryLogger->info(
                    sprintf(
                        'Populated cache for %s using %s (no requests required)',
                        (string)$waypoint,
                        (string)$cachedWaypoint
                    )
                );
                $this->persistInCache($waypoint, $page, $usableCachedCommits);
                break;
            }
        }
    }

    /**
     * Check if the page is fully populated with commits.
     *
     * Or, more specifically, see if the number of the commits are greater than or equal to
     * the number we would've tried to fetch to begin with.
     */
    private function isPageFullyPopulated(array $commits): bool
    {
        return count($commits) >= CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE;
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
