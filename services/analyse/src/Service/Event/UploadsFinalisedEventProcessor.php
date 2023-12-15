<?php

namespace App\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\ReportComparison;
use App\Model\ReportInterface;
use App\Model\ReportWaypoint;
use App\Service\CachingCoverageAnalyserService;
use App\Service\CoverageAnalyserServiceInterface;
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
                $event->getProvider(),
                $event->getOwner(),
                $event->getRepository(),
                $event->getRef(),
                $event->getCommit(),
                $event->getPullRequest(),
                $event->getBaseRef(),
                $event->getBaseCommit(),
                $coverageReport->getCoveragePercentage(),
                new DateTimeImmutable()
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
        ReportComparison|null $comparison
    ): bool {
        $lines = $coverageReport->getDiffLineCoverage()
            ->getLines();

        $annotations = $this->annotationGrouperService->generateAnnotations(
            $uploadsFinalised,
            $coverageReport->getDiff(),
            $lines,
            $coverageReport->getLatestSuccessfulUpload() ?? $uploadsFinalised->getEventTime()
        );

        return $this->sqsMessageClient->queuePublishableMessage(
            new PublishableMessageCollection(
                $uploadsFinalised,
                [
                    new PublishablePullRequestMessage(
                        $uploadsFinalised,
                        $coverageReport->getCoveragePercentage(),
                        $comparison?->getCoverageChange(),
                        $coverageReport->getDiffCoveragePercentage(),
                        count($coverageReport->getUploads()->getSuccessfulUploads()),
                        (array)$this->serializer->normalize(
                            $coverageReport->getTagCoverage()
                                ->getTags()
                        ),
                        (array)$this->serializer->normalize(
                            $coverageReport->getLeastCoveredDiffFiles()
                                ->getFiles()
                        ),
                        $coverageReport->getLatestSuccessfulUpload() ?? $uploadsFinalised->getEventTime()
                    ),
                    new PublishableCheckRunMessage(
                        $uploadsFinalised,
                        PublishableCheckRunStatus::SUCCESS,
                        $annotations,
                        $coverageReport->getCoveragePercentage(),
                        $comparison?->getCoverageChange(),
                        $coverageReport->getLatestSuccessfulUpload() ?? $uploadsFinalised->getEventTime()
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
    private function generateCoverageReport(EventInterface $event): array
    {
        $comparison = null;

        $headWaypoint = $this->coverageAnalyserService->getWaypointFromEvent($event);
        $headReport = $this->coverageAnalyserService->analyse($headWaypoint);

        // TODO: We should use the parent commit(s) as the base if its a push rather
        //  than a pull request
        $baseRef = $event->getBaseRef();
        $baseCommit = $event->getBaseCommit();

        if ($baseRef && $baseCommit) {
            // Build the base using the recorded base ref and commit as the comparison
            $baseWaypoint = new ReportWaypoint(
                $event->getProvider(),
                $event->getOwner(),
                $event->getRepository(),
                $baseRef,
                $baseCommit,
                null
            );

            $comparison = $this->coverageAnalyserService->compare(
                $this->coverageAnalyserService->analyse($baseWaypoint),
                $headReport
            );
        }


        return [$headReport, $comparison];
    }

    public static function getEvent(): string
    {
        return Event::UPLOADS_FINALISED->value;
    }
}
