<?php

namespace App\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\PublishableCoverageDataInterface;
use App\Query\Result\LineCoverageQueryResult;
use App\Service\CoverageAnalyserService;
use Bref\Event\EventBridge\EventBridgeEvent;
use JsonException;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\LineState;
use Packages\Models\Model\Event\PipelineComplete;
use Packages\Models\Model\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class PipelineCompleteEventProcessor implements EventProcessorInterface
{
    /**
     * @param SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer
     */
    public function __construct(
        private readonly LoggerInterface $eventProcessorLogger,
        private readonly SerializerInterface $serializer,
        private readonly CoverageAnalyserService $coverageAnalyserService,
        private readonly SqsMessageClient $sqsEventClient,
        private readonly EventBridgeEventClient $eventBridgeEventService
    ) {
    }

    public function process(EventBridgeEvent $event): void
    {
        try {
            $pipelineComplete = $this->serializer->denormalize(
                $event->getDetail(),
                PipelineComplete::class
            );

            $this->eventProcessorLogger->info(
                sprintf(
                    'Starting analysis on %s.',
                    (string)$pipelineComplete
                )
            );

            $coverageData = $this->coverageAnalyserService->analyse($pipelineComplete);

            $successful = $this->queueMessages($pipelineComplete, $coverageData);

            if (!$successful) {
                $this->eventProcessorLogger->critical(
                    sprintf(
                        'Attempt to publish coverage for %s was unsuccessful.',
                        (string)$pipelineComplete
                    )
                );

                $this->eventBridgeEventService->publishEvent(
                    CoverageEvent::ANALYSE_FAILURE,
                    $pipelineComplete
                );

                return;
            }

            $this->eventBridgeEventService->publishEvent(
                CoverageEvent::ANALYSIS_ON_PIPELINE_COMPLETE_SUCCESS,
                [
                    'pipelineComplete' => $pipelineComplete,
                    'coveragePercentage' => $coverageData->getCoveragePercentage()
                ]
            );
        } catch (JsonException $e) {
            $this->eventProcessorLogger->critical(
                'Exception while parsing event details.',
                [
                    'exception' => $e,
                    'event' => $event->toArray(),
                ]
            );
        }
    }

    /**
     * Write all of the publishable coverage data messages onto the queue,
     * ready to be picked up and published to the version control provider.
     *
     * Right now, this is:
     * 2. A check run
     * 3. A collection of check run annotations, linked to each uncovered line of
     *    the diff
     */
    private function queueMessages(
        PipelineComplete $pipelineComplete,
        PublishableCoverageDataInterface $publishableCoverageData
    ): bool {
        $annotations = array_map(
            function (LineCoverageQueryResult $line) use ($pipelineComplete, $publishableCoverageData) {
                if ($line->getState() !== LineState::UNCOVERED) {
                    return null;
                }

                return new PublishableCheckAnnotationMessage(
                    $pipelineComplete,
                    $line->getFileName(),
                    $line->getLineNumber(),
                    $line->getState(),
                    $publishableCoverageData->getLatestSuccessfulUpload() ?? $pipelineComplete->getCompletedAt()
                );
            },
            $publishableCoverageData->getDiffLineCoverage()->getLines()
        );

        return $this->sqsEventClient->queuePublishableMessage(
            new PublishableCheckRunMessage(
                $pipelineComplete,
                array_filter($annotations),
                $publishableCoverageData->getCoveragePercentage(),
                $publishableCoverageData->getLatestSuccessfulUpload() ?? $pipelineComplete->getCompletedAt()
            )
        );
    }
}
