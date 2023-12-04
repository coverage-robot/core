<?php

namespace App\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\PublishableCoverageDataInterface;
use App\Query\Result\LineCoverageQueryResult;
use App\Service\CoverageAnalyserService;
use DateTimeImmutable;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Model\AnalyseFailure;
use Packages\Event\Model\CoverageFinalised;
use Packages\Event\Model\UploadsFinalised;
use Packages\Message\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use Packages\Message\PublishableMessage\PublishableMessageCollection;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Packages\Models\Enum\LineState;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class UploadsFinalisedEventProcessor implements EventProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface $eventProcessorLogger,
        private readonly SerializerInterface&NormalizerInterface $serializer,
        private readonly CoverageAnalyserService $coverageAnalyserService,
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

        $coverageData = $this->coverageAnalyserService->analyse($event);

        $successful = $this->queueFinalCheckRun(
            $event,
            $coverageData
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
                $coverageData->getCoveragePercentage(),
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
        PublishableCoverageDataInterface $publishableCoverageData
    ): bool {
        $annotations = array_map(
            static function (LineCoverageQueryResult $line) use ($uploadsFinalised, $publishableCoverageData) {

                if ($line->getState() !== LineState::UNCOVERED) {
                    return null;
                }

                return new PublishableCheckAnnotationMessage(
                    $uploadsFinalised,
                    $line->getFileName(),
                    $line->getLineNumber(),
                    $line->getState(),
                    $publishableCoverageData->getLatestSuccessfulUpload() ?? $uploadsFinalised->getEventTime()
                );
            },
            $publishableCoverageData->getDiffLineCoverage()->getLines()
        );

        return $this->sqsMessageClient->queuePublishableMessage(
            new PublishableMessageCollection(
                $uploadsFinalised,
                [
                    new PublishablePullRequestMessage(
                        $uploadsFinalised,
                        $publishableCoverageData->getCoveragePercentage(),
                        $publishableCoverageData->getDiffCoveragePercentage(),
                        count($publishableCoverageData->getSuccessfulUploads()),
                        (array)$this->serializer->normalize($publishableCoverageData->getTagCoverage()->getTags()),
                        (array)$this->serializer->normalize(
                            $publishableCoverageData->getLeastCoveredDiffFiles()->getFiles()
                        ),
                        $publishableCoverageData->getLatestSuccessfulUpload() ?? $uploadsFinalised->getEventTime()
                    ),
                    new PublishableCheckRunMessage(
                        $uploadsFinalised,
                        PublishableCheckRunStatus::SUCCESS,
                        array_filter($annotations),
                        $publishableCoverageData->getCoveragePercentage(),
                        $publishableCoverageData->getLatestSuccessfulUpload() ?? $uploadsFinalised->getEventTime()
                    )
                ]
            ),
        );
    }

    public static function getEvent(): string
    {
        return Event::UPLOADS_FINALISED->value;
    }
}
