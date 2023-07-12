<?php

namespace App\Handler;

use App\Service\CoverageAnalyserService;
use App\Service\CoveragePublisherService;
use App\Service\EventBridgeEventService;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use JsonException;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;

class EventHandler extends EventBridgeHandler
{
    public function __construct(
        private readonly LoggerInterface $handlerLogger,
        private readonly CoverageAnalyserService $coverageAnalyserService,
        private readonly CoveragePublisherService $coveragePublisherService,
        private readonly EventBridgeEventService $eventBridgeEventService
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

            $successful = $this->coveragePublisherService->publish($upload, $coverageData);

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
}
