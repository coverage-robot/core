<?php

namespace App\Handler;

use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Event\Model\EventInterface;
use Packages\Event\Service\EventProcessorService;
use Packages\Event\Service\EventProcessorServiceInterface;
use Packages\Telemetry\Service\TraceContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class EventHandler extends EventBridgeHandler
{
    public function __construct(
        private readonly LoggerInterface $eventHandlerLogger,
        #[Autowire(service: EventProcessorService::class)]
        private readonly EventProcessorServiceInterface $eventProcessor,
        private readonly SerializerInterface&DenormalizerInterface $serializer
    ) {
    }

    #[Override]
    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        TraceContext::setTraceHeaderFromContext($context);

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

        $this->eventProcessor->process(
            Event::from($event->getDetailType()),
            $this->serializer->denormalize(
                $event->getDetail(),
                EventInterface::class
            )
        );
    }
}
