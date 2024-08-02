<?php

namespace App\Event;

use App\Exception\AnalysisException;
use App\Model\CoverageReportComparison;
use App\Model\CoverageReportInterface;
use App\Service\CachingCoverageAnalyserService;
use App\Service\CoverageAnalyserServiceInterface;
use App\Service\CoverageComparisonServiceInterface;
use App\Service\LineGroupingService;
use DateTimeImmutable;
use Override;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Model\LineCommentType;
use Packages\Configuration\Service\SettingServiceInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\EventSource;
use Packages\Event\Client\EventBusClient;
use Packages\Event\Client\EventBusClientInterface;
use Packages\Event\Model\CoverageFailed;
use Packages\Event\Model\CoverageFinalised;
use Packages\Event\Model\UploadsFinalised;
use Packages\Event\Model\UtilisationAmendment;
use Packages\Event\Processor\EventProcessorInterface;
use Packages\Message\Client\PublishClient;
use Packages\Message\Client\SqsClientInterface;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use Packages\Message\PublishableMessage\PublishableLineCommentMessageCollection;
use Packages\Message\PublishableMessage\PublishableMessageCollection;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Packages\Telemetry\Service\MetricServiceInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class UploadsFinalisedEventProcessor implements EventProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface $eventProcessorLogger,
        private readonly MetricServiceInterface $metricService,
        private readonly SerializerInterface&NormalizerInterface $serializer,
        #[Autowire(service: CachingCoverageAnalyserService::class)]
        private readonly CoverageAnalyserServiceInterface $coverageAnalyserService,
        private readonly CoverageComparisonServiceInterface $coverageComparisonService,
        private readonly LineGroupingService $lineGroupingService,
        private readonly SettingServiceInterface $settingService,
        #[Autowire(service: EventBusClient::class)]
        private readonly EventBusClientInterface $eventBusClient,
        #[Autowire(service: PublishClient::class)]
        private readonly SqsClientInterface $publishClient
    ) {
    }

    #[Override]
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

        try {
            [$coverageReport, $comparison] = $this->generateCoverageReport($event);

            $totalReportSize = $this->getAndRecordReportSize($comparison ?? $coverageReport);

            $successful = $this->queueCoverageReport(
                $event,
                $coverageReport,
                $comparison
            );

            if ($successful) {
                /**
                 * Broadcast a new amendment to the utilisation data for the project.
                 *
                 * This will let any services know that the project's utilisation has increased
                 */
                $this->eventBusClient->fireEvent(
                    EventSource::ANALYSE,
                    new UtilisationAmendment(
                        provider: $event->getProvider(),
                        owner: $event->getOwner(),
                        repository: $event->getRepository(),
                        ref: $event->getRef(),
                        commit: $event->getCommit(),
                        pullRequest: $event->getPullRequest(),
                        analysedCoverage: $totalReportSize,
                    )
                );

                /**
                 * Broadcast the finalised coverage event so that other services can act on it.
                 */
                $this->eventBusClient->fireEvent(
                    EventSource::ANALYSE,
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

            $this->eventProcessorLogger->critical(
                sprintf(
                    'Attempt to publish coverage for %s was unsuccessful.',
                    (string)$event
                )
            );
        } catch (AnalysisException $analysisException) {
            $this->eventProcessorLogger->critical(
                sprintf(
                    'Attempt to publish coverage for %s resulted in an exception.',
                    (string)$event
                ),
                [
                    'exception' => $analysisException
                ]
            );

            $coverageReport = null;
            $comparison = null;
        }

        // If we've reached this point, we've failed to publish the coverage report. We should broadcast that
        // in case any services are subscribed to the event.
        $this->eventBusClient->fireEvent(
            EventSource::ANALYSE,
            new CoverageFailed(
                provider: $event->getProvider(),
                owner: $event->getOwner(),
                repository: $event->getRepository(),
                ref: $event->getRef(),
                commit: $event->getCommit(),
                pullRequest: $event->getPullRequest(),
                baseRef: $comparison?->getBaseReport()
                    ->getWaypoint()
                    ->getRef(),
                baseCommit: $comparison?->getBaseReport()
                    ->getWaypoint()
                    ->getCommit()
            )
        );

        return false;
    }

    /**
     * Write all of the publishable coverage data messages onto the queue for the _final_ check
     * run state, ready to be picked up and published to the version control provider.
     *
     * Right now, this is:
     * 1. A pull request message
     * 2. A complete check run
     * 3. A collection of line comments, linked to each uncovered line of the diff
     */
    private function queueCoverageReport(
        UploadsFinalised $uploadsFinalised,
        CoverageReportInterface $coverageReport,
        ?CoverageReportComparison $comparison
    ): bool {
        $validUntil = $coverageReport->getLatestSuccessfulUpload() ??
            $uploadsFinalised->getEventTime();

        $publishableMessages = [
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
                $validUntil
            ),
        ];

        $lineComments = $this->buildLineCommentCollection(
            $uploadsFinalised,
            $coverageReport,
            $validUntil
        );

        if ($lineComments instanceof PublishableLineCommentMessageCollection) {
            $publishableMessages[] = $lineComments;
        }

        return $this->publishClient->dispatch(
            new PublishableMessageCollection(
                $uploadsFinalised,
                $publishableMessages
            ),
        );
    }

    /**
     * Generate a coverage coverage report for the current commit, and optionally (if
     * the event has the required data) a comparison report against the base commit.
     *
     * @return array{0: CoverageReportInterface, 1: CoverageReportComparison|null}
     */
    private function generateCoverageReport(UploadsFinalised $event): array
    {
        $headWaypoint = $this->coverageAnalyserService->getWaypointFromEvent($event);

        $headReport = $this->coverageAnalyserService->analyse($headWaypoint);

        $comparison = $this->coverageComparisonService->getComparisonForCoverageReport(
            $headReport,
            $event
        );

        return [$headReport, $comparison];
    }

    /**
     * Record the total size (aka the total number of lines uploaded to each report) as a metric.
     */
    private function getAndRecordReportSize(CoverageReportInterface|CoverageReportComparison $coverageReport): int
    {
        $waypoint = $coverageReport instanceof CoverageReportComparison ?
            $coverageReport->getHeadReport()->getWaypoint() :
            $coverageReport->getWaypoint();

        $totalReportSize = $coverageReport->getSize();

        $this->metricService->increment(
            metric: 'CoverageReportSize',
            value: $totalReportSize,
            dimensions: [['provider', 'owner'], ['provider', 'owner', 'repository']],
            properties: [
                'provider' => $waypoint->getProvider(),
                'owner' => $waypoint->getOwner(),
                'repository' => $waypoint->getRepository()
            ]
        );

        return $totalReportSize;
    }

    private function buildPullRequestMessage(
        UploadsFinalised $uploadsFinalised,
        CoverageReportInterface $coverageReport,
        ?CoverageReportComparison $comparison,
        DateTimeImmutable $validUntil
    ): PublishablePullRequestMessage {
        return new PublishablePullRequestMessage(
            event: $uploadsFinalised,
            coveragePercentage: $coverageReport->getCoveragePercentage(),
            diffCoveragePercentage: $coverageReport->getDiffCoveragePercentage(),
            diffUncoveredLines: $coverageReport->getDiffUncoveredLines(),
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
            uncoveredLinesChange: $comparison?->getUncoveredLinesChange(),
            coverageChange: $comparison?->getCoverageChange(),
            validUntil: $validUntil
        );
    }

    public function buildCheckRunMessage(
        UploadsFinalised $uploadsFinalised,
        CoverageReportInterface $coverageReport,
        ?CoverageReportComparison $comparison,
        DateTimeImmutable $validUntil
    ): PublishableCheckRunMessage {
        return new PublishableCheckRunMessage(
            event: $uploadsFinalised,
            status: PublishableCheckRunStatus::SUCCESS,
            coveragePercentage: $coverageReport->getCoveragePercentage(),
            baseCommit: $comparison?->getBaseReport()
                ->getWaypoint()
                ->getCommit(),
            coverageChange: $comparison?->getCoverageChange(),
            validUntil: $validUntil
        );
    }

    public function buildLineCommentCollection(
        UploadsFinalised $uploadsFinalised,
        CoverageReportInterface $coverageReport,
        DateTimeImmutable $validUntil
    ): ?PublishableLineCommentMessageCollection {
        /** @var LineCommentType $lineCommentType */
        $lineCommentType = $this->settingService->get(
            $uploadsFinalised->getProvider(),
            $uploadsFinalised->getOwner(),
            $uploadsFinalised->getRepository(),
            SettingKey::LINE_COMMENT_TYPE
        );

        if ($lineCommentType !== LineCommentType::HIDDEN) {
            $lineComments = $this->lineGroupingService->generateComments(
                $uploadsFinalised,
                $coverageReport->getWaypoint()
                    ->getDiff(),
                $coverageReport->getDiffLineCoverage()
                    ->getLines(),
                $validUntil
            );

            if ($lineComments === []) {
                return null;
            }

            return new PublishableLineCommentMessageCollection(
                $uploadsFinalised,
                $lineComments
            );
        }

        return null;
    }

    #[Override]
    public static function getEvent(): string
    {
        return Event::UPLOADS_FINALISED->value;
    }
}
