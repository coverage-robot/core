<?php

namespace App\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\ReportComparison;
use App\Model\ReportInterface;
use App\Service\CachingCoverageAnalyserService;
use App\Service\CoverageAnalyserServiceInterface;
use App\Service\CoverageComparisonService;
use App\Service\LineGroupingService;
use DateTimeImmutable;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Service\SettingService;
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
        private readonly CoverageComparisonService $coverageComparisonService,
        private readonly LineGroupingService $annotationGrouperService,
        private readonly SettingService $settingService,
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

        $successful = $this->queueCoverageReport(
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
                baseRef: $comparison?->getBaseReport()
                    ->getWaypoint()
                    ->getRef(),
                baseCommit: $comparison?->getBaseReport()
                    ->getWaypoint()
                    ->getCommit()
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
    private function queueCoverageReport(
        UploadsFinalised $uploadsFinalised,
        ReportInterface $coverageReport,
        ?ReportComparison $comparison
    ): bool {
        $annotations = [];
        $validUntil = $coverageReport->getLatestSuccessfulUpload() ??
            $uploadsFinalised->getEventTime();

        $shouldGenerateAnnotations = $this->settingService->get(
            $uploadsFinalised->getProvider(),
            $uploadsFinalised->getOwner(),
            $uploadsFinalised->getRepository(),
            SettingKey::LINE_ANNOTATION
        );

        if ($shouldGenerateAnnotations === true) {
            $annotations = $this->annotationGrouperService->generateAnnotations(
                $uploadsFinalised,
                $coverageReport->getWaypoint()
                    ->getDiff(),
                $coverageReport->getDiffLineCoverage()
                    ->getLines(),
                $validUntil
            );
        }

        return $this->sqsMessageClient->queuePublishableMessage(
            new PublishableMessageCollection(
                $uploadsFinalised,
                [
                    $this->buildPullRequestMessage(
                        $uploadsFinalised,
                        $coverageReport,
                        $comparison,
                        $validUntil
                    ),
                    $this->buildCheckRunMessage(
                        $uploadsFinalised,
                        $coverageReport,
                        $comparison,
                        $validUntil,
                        $annotations
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
        $headWaypoint = $this->coverageAnalyserService->getWaypointFromEvent($event);

        $comparison = $this->coverageComparisonService->getSuitableComparisonForWaypoint(
            $headWaypoint,
            $event
        );

        $headReport = $comparison?->getHeadReport();

        if ($headReport === null) {
            // We weren't able to come up with a suitable comparison, so just generate
            // a report of the current commit (the head), and move on.
            $headReport = $this->coverageAnalyserService->analyse($headWaypoint);
        }

        return [$headReport, $comparison];
    }

    private function buildPullRequestMessage(
        UploadsFinalised $uploadsFinalised,
        ReportInterface $coverageReport,
        ?ReportComparison $comparison,
        DateTimeImmutable $validUntil
    ): PublishablePullRequestMessage {
        return new PublishablePullRequestMessage(
            event: $uploadsFinalised,
            coveragePercentage: $coverageReport->getCoveragePercentage(),
            diffCoveragePercentage: $coverageReport->getDiffCoveragePercentage(),
            successfulUploads: count($coverageReport->getUploads()
                ->getSuccessfulUploads()),
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
            validUntil: $validUntil
        );
    }

    public function buildCheckRunMessage(
        UploadsFinalised $uploadsFinalised,
        ReportInterface $coverageReport,
        ?ReportComparison $comparison,
        DateTimeImmutable $validUntil,
        array $annotations
    ): PublishableCheckRunMessage {
        return new PublishableCheckRunMessage(
            event: $uploadsFinalised,
            status: PublishableCheckRunStatus::SUCCESS,
            coveragePercentage: $coverageReport->getCoveragePercentage(),
            annotations: $annotations,
            baseCommit: $comparison?->getBaseReport()
                ->getWaypoint()
                ->getCommit(),
            coverageChange: $comparison?->getCoverageChange(),
            validUntil: $validUntil
        );
    }

    public static function getEvent(): string
    {
        return Event::UPLOADS_FINALISED->value;
    }
}
