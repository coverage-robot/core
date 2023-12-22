<?php

namespace App\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\ReportComparison;
use App\Model\ReportInterface;
use App\Model\ReportWaypoint;
use App\Service\CachingCoverageAnalyserService;
use App\Service\CoverageAnalyserServiceInterface;
use App\Service\History\CommitHistoryService;
use App\Service\LineGroupingService;
use DateTimeImmutable;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Model\AnalyseFailure;
use Packages\Event\Model\CoverageFinalised;
use Packages\Event\Model\UploadsFinalised;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use Packages\Message\PublishableMessage\PublishableMessageCollection;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class UploadsFinalisedEventProcessor implements EventProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface $eventProcessorLogger,
        private readonly SerializerInterface&NormalizerInterface $serializer,
        #[Autowire(service: CachingCoverageAnalyserService::class)]
        private readonly CoverageAnalyserServiceInterface $coverageAnalyserService,
        private readonly LineGroupingService $annotationGrouperService,
        private readonly EventBridgeEventClient $eventBridgeEventService,
        private readonly SqsMessageClient $sqsMessageClient
    ) {
    }

    public function process(EventInterface $event): bool
    {
        if (!$event instanceof UploadsFinalised) {
            throw new RuntimeException(
                sprintf(
                    'Event is not an instance of %s',
                    UploadsFinalised::class
                )
            );
        }

        [$coverageReport, $comparison] = $this->generateCoverageReport($event);

        $successful = $this->queueFinalCheckRun(
            $event,
            $coverageReport,
            $comparison
        );

        if (!$successful) {
            $this->eventProcessorLogger->critical(
                sprintf(
                    'Attempt to publish coverage for %s was unsuccessful.',
                    (string)$event
                )
            );

            $this->eventBridgeEventService->publishEvent(
                new AnalyseFailure(
                    $event,
                    new DateTimeImmutable()
                )
            );

            return false;
        }

        $this->eventBridgeEventService->publishEvent(
            new CoverageFinalised(
                provider: $event->getProvider(),
                owner: $event->getOwner(),
                repository: $event->getRepository(),
                ref: $event->getRef(),
                commit: $event->getCommit(),
                coveragePercentage: $coverageReport->getCoveragePercentage(),
                pullRequest: $event->getPullRequest(),
                baseRef: $event->getBaseCommit(),
                baseCommit: $event->getBaseRef()
            )
        );

        return true;
    }

    /**
     * Write all of the publishable coverage data messages onto the queue for the _final_ check
     * run state, ready to be picked up and published to the version control provider.
     *
     * Right now, this is:
     * 2. A complete check run
     * 3. A collection of check run annotations, linked to each uncovered line of
     *    the diff
     */
    private function queueFinalCheckRun(
        UploadsFinalised $uploadsFinalised,
        ReportInterface $coverageReport,
        ?ReportComparison $comparison
    ): bool {
        $lines = $coverageReport->getDiffLineCoverage()
            ->getLines();

        $annotations = $this->annotationGrouperService->generateAnnotations(
            $uploadsFinalised,
            $coverageReport->getWaypoint()
                ->getDiff(),
            $lines,
            $coverageReport->getLatestSuccessfulUpload() ?? $uploadsFinalised->getEventTime()
        );

        return $this->sqsMessageClient->queuePublishableMessage(
            new PublishableMessageCollection(
                $uploadsFinalised,
                [
                    new PublishablePullRequestMessage(
                        event: $uploadsFinalised,
                        coveragePercentage: $coverageReport->getCoveragePercentage(),
                        diffCoveragePercentage: $coverageReport->getDiffCoveragePercentage(),
                        successfulUploads: count($coverageReport->getUploads()->getSuccessfulUploads()),
                        tagCoverage: (array)$this->serializer->normalize(
                            $coverageReport->getTagCoverage()
                                ->getTags()
                        ),
                        leastCoveredDiffFiles: (array)$this->serializer->normalize(
                            $coverageReport->getLeastCoveredDiffFiles()
                                ->getFiles()
                        ),
                        baseCommit: $comparison?->getBaseReport()
                            ->getWaypoint()
                            ->getCommit(),
                        coverageChange: $comparison?->getCoverageChange(),
                        validUntil: $coverageReport->getLatestSuccessfulUpload() ?? $uploadsFinalised->getEventTime()
                    ),
                    new PublishableCheckRunMessage(
                        event: $uploadsFinalised,
                        status: PublishableCheckRunStatus::SUCCESS,
                        coveragePercentage: $coverageReport->getCoveragePercentage(),
                        annotations: $annotations,
                        baseCommit: $comparison?->getBaseReport()
                            ->getWaypoint()
                            ->getCommit(),
                        coverageChange: $comparison?->getCoverageChange(),
                        validUntil: $coverageReport->getLatestSuccessfulUpload() ?? $uploadsFinalised->getEventTime()
                    )
                ]
            ),
        );
    }

    /**
     * Generate a coverage coverage report for the current commit, and optionally (if
     * the event has the required data) a comparison report against the base commit.
     *
     * @return array{0: ReportInterface, 1: ReportComparison|null}
     */
    private function generateCoverageReport(UploadsFinalised $event): array
    {
        $comparison = null;

        $headWaypoint = $this->coverageAnalyserService->getWaypointFromEvent($event);
        $headReport = $this->coverageAnalyserService->analyse($headWaypoint);

        $baseWaypoint = $this->getBaseWaypointForComparison($headWaypoint, $event);

        if ($baseWaypoint instanceof ReportWaypoint) {
            // We've been able to generate base waypoint to compare to, so we'll generate
            // a comparison report.
            $comparison = $this->coverageAnalyserService->compare(
                $this->coverageAnalyserService->analyse($baseWaypoint),
                $headReport
            );
        }

        return [$headReport, $comparison];
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
        UploadsFinalised $event
    ): ?ReportWaypoint {
        $baseRef = $event->getBaseRef();

        if ($baseRef) {
            // We've got a base ref, meaning theres a good chance we can do a larger
            // comparison across commits (i.e. PR comparisons, etc)
            for ($page = 1; $page <= 5; ++$page) {
                $history = $headWaypoint->getHistory($page);

                foreach ($history as $commit) {
                    if ($commit['ref'] !== $headWaypoint->getRef() || $commit['merged']) {
                        $this->eventProcessorLogger->info(
                            sprintf(
                                'Extracted %s from history to use as the base commit for comparison to %s',
                                $commit['commit'],
                                (string)$headWaypoint
                            )
                        );

                        // Use the latest commit in the history that is on the base ref as the preferred option.
                        // This ensures the commit is in the history (i.e. not newer than the head commit)
                        return $this->coverageAnalyserService->getWaypoint(
                            $event->getProvider(),
                            $event->getOwner(),
                            $event->getRepository(),
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

            $baseCommit = $event->getBaseCommit();
            if ($baseCommit !== null) {
                $this->eventProcessorLogger->info(
                    sprintf(
                        'Extracted %s from event base to use as the base commit for comparison to %s',
                        $baseCommit,
                        (string)$headWaypoint
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
        }

        if ($event->getParent() !== []) {
            $this->eventProcessorLogger->info(
                sprintf(
                    'Extracted %s from event parents to use as the base commit for comparison to %s',
                    (string)$event->getParent()[0],
                    (string)$headWaypoint
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
                (string)$event->getParent()[0],
            );
        }

        $this->eventProcessorLogger->warning(
            sprintf(
                'Unable to find base commit for comparison to %s',
                (string)$headWaypoint
            )
        );

        return null;
    }

    public static function getEvent(): string
    {
        return Event::UPLOADS_FINALISED->value;
    }
}
