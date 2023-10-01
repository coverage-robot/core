<?php

namespace App\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\PublishableCoverageDataInterface;
use App\Query\Result\FileCoverageQueryResult;
use App\Query\Result\TagCoverageQueryResult;
use App\Service\CoverageAnalyserService;
use Bref\Event\EventBridge\EventBridgeEvent;
use JsonException;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\PublishableMessage\PublishablePullRequestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class IngestSuccessEventProcessor implements EventProcessorInterface
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
            $upload = $this->serializer->denormalize(
                $event->getDetail(),
                Upload::class
            );

            $this->eventProcessorLogger->info(
                sprintf(
                    'Starting analysis on %s.',
                    (string)$upload
                )
            );

            $coverageData = $this->coverageAnalyserService->analyse($upload);

            $successful = $this->queuePullRequestMessage($upload, $coverageData);

            if (!$successful) {
                $this->eventProcessorLogger->critical(
                    sprintf(
                        'Attempt to publish coverage for %s was unsuccessful.',
                        (string)$upload
                    )
                );

                $this->eventBridgeEventService->publishEvent(
                    CoverageEvent::ANALYSE_FAILURE,
                    $upload
                );

                return;
            }

            $this->eventBridgeEventService->publishEvent(
                CoverageEvent::ANALYSIS_ON_NEW_UPLOAD_SUCCESS,
                [
                    'upload' => $this->serializer->serialize($upload, 'json'),
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
     * Queue up a pull request comment to be published to the version control provider.
     */
    private function queuePullRequestMessage(
        Upload $upload,
        PublishableCoverageDataInterface $publishableCoverageData
    ): bool {
        return $this->sqsEventClient->queuePublishableMessage(
            new PublishablePullRequestMessage(
                $upload,
                $publishableCoverageData->getCoveragePercentage(),
                $publishableCoverageData->getDiffCoveragePercentage(),
                count($publishableCoverageData->getSuccessfulUploads()),
                count($publishableCoverageData->getPendingUploads()),
                array_map(
                    function (TagCoverageQueryResult $tag) {
                        return [
                            'tag' => [
                                'name' => $tag->getTag()->getName(),
                                'commit' => $tag->getTag()->getCommit(),
                            ],
                            'coveragePercentage' => $tag->getCoveragePercentage(),
                            'lines' => $tag->getLines(),
                            'covered' => $tag->getCovered(),
                            'partial' => $tag->getPartial(),
                            'uncovered' => $tag->getUncovered(),
                        ];
                    },
                    $publishableCoverageData->getTagCoverage()->getTags()
                ),
                array_map(
                    function (FileCoverageQueryResult $file) {
                        return [
                            'fileName' => $file->getFileName(),
                            'coveragePercentage' => $file->getCoveragePercentage(),
                            'lines' => $file->getLines(),
                            'covered' => $file->getCovered(),
                            'partial' => $file->getPartial(),
                            'uncovered' => $file->getUncovered(),
                        ];
                    },
                    $publishableCoverageData->getLeastCoveredDiffFiles()->getFiles()
                ),
                $publishableCoverageData->getLatestSuccessfulUpload() ?? $upload->getIngestTime()
            )
        );
    }
}
