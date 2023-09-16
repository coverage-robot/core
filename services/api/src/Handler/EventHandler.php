<?php

namespace App\Handler;

use App\Service\Event\AnalysisOnNewUploadSuccessEventProcessor;
use App\Service\Event\EventProcessorInterface;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class EventHandler extends EventBridgeHandler implements ServiceSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $eventHandlerLogger,
        private readonly ContainerInterface $container
    ) {
    }

    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        $handlerClass = match ($event->getDetailType()) {
            CoverageEvent::ANALYSIS_ON_NEW_UPLOAD_SUCCESS->value => AnalysisOnNewUploadSuccessEventProcessor::class,
            default => null,
        };

        if ($handlerClass === null) {
            $this->eventHandlerLogger->warning(
                'Event skipped as it was not a known event.',
                [
                    'detailType' => $event->getDetailType(),
                    'detail' => $event->getDetail(),
                ]
            );
            return;
        }

        /** @var EventProcessorInterface $handler */
        $handler = $this->container->get($handlerClass);

        $handler->process($event);
    }

    public static function getSubscribedServices(): array
    {
        return [
            AnalysisOnNewUploadSuccessEventProcessor::class
        ];
    }
}
