<?php

namespace App\Handler;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\PublishableCoverageDataInterface;
use App\Query\Result\FileCoverageQueryResult;
use App\Query\Result\LineCoverageQueryResult;
use App\Query\Result\TagCoverageQueryResult;
use App\Service\CoverageAnalyserService;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use JsonException;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\LineState;
use Packages\Models\Model\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
use Packages\Models\Model\PublishableMessage\PublishableMessageCollection;
use Packages\Models\Model\PublishableMessage\PublishablePullRequestMessage;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;

class EventHandler extends EventBridgeHandler
{
    public function __construct(
        private readonly LoggerInterface $handlerLogger,
        private readonly CoverageAnalyserService $coverageAnalyserService,
        private readonly SqsMessageClient $sqsEventClient,
        private readonly EventBridgeEventClient $eventBridgeEventService
    ) {
    }

    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        try {
            /** @var array $detail */
            $detail = $event->getDetail();

            $upload = Upload::from($detail);

            $this->handlerLogger->info(sprintf('Starting analysis on %s.', (string)$upload));

            $coverageData = $this->coverageAnalyserService->analyse($upload);

            $successful = $this->queueMessages($upload, $coverageData);

            if (!$successful) {
                $this->handlerLogger->critical(
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
                CoverageEvent::ANALYSE_SUCCESS,
                [
                    'upload' => $upload->jsonSerialize(),
                    'coveragePercentage' => $coverageData->getCoveragePercentage()
                ]
            );
        } catch (JsonException $e) {
            $this->handlerLogger->critical(
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
     * 1. A pull request comment
     * 2. A check run
     * 3. A collection of check run annotations, linked to each uncovered line of
     *    the diff
     */
    private function queueMessages(Upload $upload, PublishableCoverageDataInterface $publishableCoverageData): bool
    {
        $messages = [
            new PublishablePullRequestMessage(
                $upload,
                $publishableCoverageData->getCoveragePercentage(),
                $publishableCoverageData->getDiffCoveragePercentage(),
                $publishableCoverageData->getTotalUploads(),
                array_map(
                    function (TagCoverageQueryResult $tag) {
                        return [
                            'tag' => $tag->getTag()->getName(),
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
                $upload->getIngestTime()
            ),
            new PublishableCheckRunMessage(
                $upload,
                $publishableCoverageData->getCoveragePercentage(),
                $upload->getIngestTime()
            ),
        ];

        $annotations = array_map(
            function (LineCoverageQueryResult $line) use ($upload) {
                if ($line->getState() !== LineState::UNCOVERED) {
                    return null;
                }

                return new PublishableCheckAnnotationMessage(
                    $upload,
                    $line->getFileName(),
                    $line->getLineNumber(),
                    $line->getState(),
                    $upload->getIngestTime()
                );
            },
            $publishableCoverageData->getDiffLineCoverage()->getLines()
        );

        $messages = array_merge(
            $messages,
            array_filter($annotations)
        );

        return $this->sqsEventClient->queuePublishableMessage(
            new PublishableMessageCollection(
                $upload,
                $messages
            )
        );
    }
}
