<?php

namespace App\Service\History;

use App\Model\ReportWaypoint;
use App\Service\ProviderAwareInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use WeakMap;

class CachingCommitHistoryService extends CommitHistoryService
{
    /**
     * @var WeakMap<ReportWaypoint, array<int, array{commit: string, merged: bool, ref: string|null}[]>>
     */
    private WeakMap $cache;

    /**
     * @param (CommitHistoryServiceInterface&ProviderAwareInterface)[] $parsers
     */
    public function __construct(
        #[TaggedIterator(
            'app.commit_history',
            defaultIndexMethod: 'getProvider'
        )]
        iterable $parsers
    ) {
        parent::__construct($parsers);

        /**
         * @var WeakMap<ReportWaypoint, array<int, array{commit: string, merged: bool, ref: string|null}[]>> $cache
         */
        $cache = new WeakMap();

        $this->cache = $cache;
    }

    public function getPrecedingCommits(ReportWaypoint $waypoint, int $page = 1): array
    {
        if (!$this->isCacheHit($waypoint, $page)) {
            $this->tryPopulatingCacheFromComparableWaypoints($waypoint, $page);
        }

        if (!$this->isCacheHit($waypoint, $page)) {
            $this->persistInCache(
                $waypoint,
                $page,
                parent::getPrecedingCommits($waypoint, $page)
            );
        }

        return $this->cache[$waypoint][$page];
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
        foreach ($this->cache as $cachedWaypoint => $history) {
            if (!$cachedWaypoint->comparable($waypoint)) {
                // This waypoint isn't comparable so definitely doesn't contain
                // any pre-cached history we can use.
                continue;
            }

            foreach ($history as $cachedPage => $commits) {
                $commitIndex = array_search(
                    $waypoint->getCommit(),
                    array_column($commits, 'commit'),
                    true
                );

                if ($commitIndex === false) {
                    // This waypoint doesn't contain the commit we're looking
                    // for, so we've not yet seen it in the cache
                    continue;
                }

                // We've found the commit we're looking for as the head, so from here
                // we can populate the cache for the current waypoint
                $perPage = CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE;
                $pageOffset = ($page - 1);

                if ($pageOffset > 0 && !$this->isCacheHit($cachedWaypoint, $cachedPage + $pageOffset)) {
                    // We've not yet populated the cache for the page we're looking for
                    // so theres not much we can do here
                    return;
                }

                $preCachedCommits = array_slice(
                    $commits,
                    $commitIndex,
                    $perPage
                );

                if (count($preCachedCommits) >= $perPage) {
                    // Our slice of the existing history is enough to fill the whole
                    // page, so we're okay to populate the cache and move on
                    $this->persistInCache($waypoint, $page, $preCachedCommits);

                    break;
                }

                if (count($commits) >= $perPage) {
                    // We've not got enough commits to fill the page, so we need to look
                    // back another page for the cached waypoint and then slice the results
                    // together
                    $preCachedCommits = array_merge(
                        $preCachedCommits,
                        array_slice(
                            $this->getPrecedingCommits($cachedWaypoint, $cachedPage + 1),
                            0,
                            $perPage - count($preCachedCommits)
                        )
                    );
                }

                $this->persistInCache($waypoint, $page, $preCachedCommits);

                break;
            }
        }
    }

    private function isCacheHit(ReportWaypoint $waypoint, int $page): bool
    {
        return isset($this->cache[$waypoint][$page]);
    }

    private function persistInCache(ReportWaypoint $waypoint, int $page, array $commits): void
    {
        $this->cache[$waypoint] = array_replace(
            $this->cache[$waypoint] ?? [],
            [
                $page => $commits
            ]
        );
    }
}
