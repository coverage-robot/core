<?php

namespace App\Handler;

use App\Service\Event\EventProcessor;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use Psr\Log\LoggerInterface;

class EventHandler extends EventBridgeHandler
{
    public function __construct(
        private readonly LoggerInterface $eventHandlerLogger,
        private readonly EventProcessor $eventProcessor
    ) {
    }

    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        $this->eventHandlerLogger->info(
            sprintf(
                'Starting to process new event for %s.',
                $event->getDetailType()
            ),
            [
                'detailType' => $event->getDetailType(),
                'detail' => $event->getDetail(),
            ]
        );

        $this->eventProcessor->process($event);
    }
}
