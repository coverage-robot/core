<?php

namespace App\Event;

use App\Model\CoverageReportComparison;
use App\Model\CoverageReportInterface;
use App\Service\CachingCoverageAnalyserService;
use App\Service\CoverageAnalyserServiceInterface;
use App\Service\CoverageComparisonServiceInterface;
use App\Service\LineGroupingService;
use DateTimeImmutable;
use Override;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Service\SettingService;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\EventSource;
use Packages\Event\Client\EventBusClient;
use Packages\Event\Model\AnalyseFailure;
use Packages\Event\Model\CoverageFinalised;
use Packages\Event\Model\UploadsFinalised;
use Packages\Event\Processor\EventProcessorInterface;
use Packages\Message\Client\PublishClient;
use Packages\Message\PublishableMessage\PublishableAnnotationInterface;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use Packages\Message\PublishableMessage\PublishableMessageCollection;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class UploadsFinalisedEventProcessor implements EventProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface $eventProcessorLogger,
        private readonly SerializerInterface&NormalizerInterface $serializer,
        #[Autowire(service: CachingCoverageAnalyserService::class)]
        private readonly CoverageAnalyserServiceInterface $coverageAnalyserService,
        private readonly CoverageComparisonServiceInterface $coverageComparisonService,
        private readonly LineGroupingService $annotationGrouperService,
        private readonly SettingService $settingService,
        private readonly EventBusClient $eventBusClient,
        private readonly PublishClient $publishClient
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

            $this->eventBusClient->fireEvent(
                EventSource::ANALYSE,
                new AnalyseFailure(
                    $event,
                    new DateTimeImmutable()
                )
            );

            return false;
        }

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
        CoverageReportInterface $coverageReport,
        ?CoverageReportComparison $comparison
    ): bool {
        $annotations = [];
        $validUntil = $coverageReport->getLatestSuccessfulUpload() ??
            $uploadsFinalised->getEventTime();

        /** @var bool $shouldGenerateAnnotations */
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

        return $this->publishClient->dispatch(
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

    /**
     * @param array<array-key, PublishableAnnotationInterface> $annotations
     */
    public function buildCheckRunMessage(
        UploadsFinalised $uploadsFinalised,
        CoverageReportInterface $coverageReport,
        ?CoverageReportComparison $comparison,
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

    #[Override]
    public static function getEvent(): string
    {
        return Event::UPLOADS_FINALISED->value;
    }
}
