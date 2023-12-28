<?php

namespace App\Service;

use App\Model\ReportComparison;
use App\Model\ReportWaypoint;
use App\Service\History\CommitHistoryService;
use Packages\Contracts\Event\BaseAwareEventInterface;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\ParentAwareEventInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CoverageComparisonService
{
    public function __construct(
        private readonly LoggerInterface $coverageComparisonService,
        #[Autowire(service: CachingCoverageAnalyserService::class)]
        private readonly CoverageAnalyserServiceInterface $coverageAnalyserService,
    ) {
    }

    /**
     * Generate a comparison report for the given head waypoint and event.
     *
     * This works out (using the head waypoint) which is the most suitable base waypoint
     * to compare against, and then generates a comparison report using the analyser.
     */
    public function getSuitableComparisonForWaypoint(
        ReportWaypoint $headWaypoint,
        EventInterface $event
    ): ?ReportComparison {
        $baseWaypoint = $this->getBaseWaypointForComparison($headWaypoint, $event);

        if ($baseWaypoint instanceof ReportWaypoint) {
            // We've been able to generate base waypoint to compare to, so we'll generate
            // a comparison report.
            return $this->coverageAnalyserService->compare(
                $this->coverageAnalyserService->analyse($baseWaypoint),
                $this->coverageAnalyserService->analyse($headWaypoint)
            );
        }

        return null;
    }

    /**
     * Get the base waypoint for the comparison report using the head waypoints history
     * and the real-time event which triggered the analysis.
     *
     * Theres three ways we can get the base waypoint (in priority order):
     * 1. Use the first commit in the history as the base commit - this is preferable as
     *    it ensures the commit we use as a comparison is not newer than the head commit
     *    we're comparing against.
     * 2. Use the base commit recorded on the event - this is generally the base of the PR
     *    and **doesn't** guarantee that the merge point is also a parent commit of the
     *    head, so _could_ produce unintended coverage results.
     * 3. Use the parent commit recorded on the event - this is generally preferred for push
     *    or merge events which won't have a base ref (because theres no independent base).
     */
    private function getBaseWaypointForComparison(
        ReportWaypoint $headWaypoint,
        EventInterface $event
    ): ?ReportWaypoint {
        if ($event instanceof BaseAwareEventInterface) {
            if (($baseWaypoint = $this->getBaseWaypointFromHistory($headWaypoint, $event)) instanceof ReportWaypoint) {
                return $baseWaypoint;
            }

            if (($baseWaypoint = $this->getBaseWaypointFromEventBase($event)) instanceof ReportWaypoint) {
                return $baseWaypoint;
            }
        }

        if (
            $event instanceof ParentAwareEventInterface &&
            ($baseWaypoint = $this->getBaseWaypointFromEventParent($event)) instanceof ReportWaypoint
        ) {
            return $baseWaypoint;
        }

        $this->coverageComparisonService->warning(
            sprintf(
                'Unable to find base commit for comparison to %s',
                (string)$headWaypoint
            )
        );

        return null;
    }

    /**
     * Look through the waypoints history to try and find a commit which exists on the base
     * ref of the event.
     */
    private function getBaseWaypointFromHistory(
        ReportWaypoint $headWaypoint,
        EventInterface&BaseAwareEventInterface $event
    ): ?ReportWaypoint {
        $baseRef = $event->getBaseRef();

        if (!$baseRef) {
            // We didn't receive sufficient base information on the event (likely because the
            // event was a push - i.e. not a pull request).
            return null;
        }

        for ($page = 1; $page <= 5; ++$page) {
            $history = $headWaypoint->getHistory($page);

            foreach ($history as $commit) {
                if (
                    $commit['ref'] !== $headWaypoint->getRef() ||
                    $commit['merged']
                ) {
                    $this->coverageComparisonService->info(
                        sprintf(
                            'Extracted %s from history to use as the base commit for comparison to %s',
                            $commit['commit'],
                            (string)$headWaypoint
                        )
                    );

                    return $this->coverageAnalyserService->getWaypoint(
                        $headWaypoint->getProvider(),
                        $headWaypoint->getOwner(),
                        $headWaypoint->getRepository(),
                        $baseRef,
                        $commit['commit'],
                    );
                }
            }

            if (count($history) < CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE) {
                // We must have hit the end of the tree as theres less commits than we
                // would have fetched
                break;
            }
        }

        return null;
    }

    /**
     * Use the base commit recorded on the event. Generally this is the base of the
     * PR, but that isn't usually ideal because the base of a PR doesnt have to be
     * a parent commit (i.e. it could be newer, and this include more coverage).
     */
    private function getBaseWaypointFromEventBase(EventInterface&BaseAwareEventInterface $event): ?ReportWaypoint
    {
        $baseRef = $event->getBaseRef();
        $baseCommit = $event->getBaseCommit();
        if (
            !$event->getPullRequest() ||
            $baseRef === null ||
            $baseCommit === null
        ) {
            // We didn't receive sufficient base information on the event (likely because the
            // event was a push - i.e. not a pull request).
            return null;
        }

        $this->coverageComparisonService->info(
            sprintf(
                'Extracted %s from event base to use as the base commit for comparison for %s',
                $baseCommit,
                $event
            )
        );

        // Use the base commit recorded on the event. Generally this is the base of the
        // PR, but that isn't usually ideal because the base of a PR doesnt have to be
        // a parent commit (i.e. it could be newer, and this include more coverage).
        return $this->coverageAnalyserService->getWaypoint(
            $event->getProvider(),
            $event->getOwner(),
            $event->getRepository(),
            $baseRef,
            $baseCommit,
        );
    }

    /**
     * Use the parent commit recorded on the event. This is generally preferred for push
     * or merge events which won't have a base ref (because theres no independent base).
     */
    private function getBaseWaypointFromEventParent(
        EventInterface&ParentAwareEventInterface $event
    ): ?ReportWaypoint {
        if ($event->getParent() === []) {
            // We didn't receive sufficient parent information on the event, so this method isn't
            // viable
            return null;
        }

        $this->coverageComparisonService->info(
            sprintf(
                'Extracted %s from event parents to use as the base commit for comparison for %s',
                $event->getParent()[0],
                (string)$event
            )
        );

        // Use the parent commits as the base comparison if theres no base
        // provided in any other means
        return $this->coverageAnalyserService->getWaypoint(
            $event->getProvider(),
            $event->getOwner(),
            $event->getRepository(),
            $event->getRef(),
            // Use the first parent commit as the base commit as this will
            // be the commit of the base in the case of a merge commit
            $event->getParent()[0],
        );
    }
}
