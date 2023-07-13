<?php

namespace App\Handler;

use App\Service\Event\AnalyseSuccessEventProcessor;
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
        $handlerClass = match (CoverageEvent::from($event->getDetailType())) {
            CoverageEvent::ANALYSE_SUCCESS => AnalyseSuccessEventProcessor::class,
            default => null,
        };

        if (!$handlerClass) {
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
            AnalyseSuccessEventProcessor::class
        ];
    }
}
