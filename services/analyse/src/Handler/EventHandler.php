<?php

namespace App\Handler;

use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use Packages\Contracts\Event\Event;
use Packages\Event\Model\EventInterface;
use Packages\Event\Service\EventProcessorService;
use Packages\Event\Service\EventProcessorServiceInterface;
use Packages\Telemetry\Service\TraceContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class EventHandler extends EventBridgeHandler
{
    public function __construct(
        #[Autowire(service: EventProcessorService::class)]
        private readonly EventProcessorServiceInterface $eventProcessorService,
        private readonly SerializerInterface&DenormalizerInterface $serializer
    ) {
    }

    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        TraceContext::setTraceHeaderFromContext($context);

        $eventType = Event::from($event->getDetailType());

        $this->eventProcessorService->process(
            $eventType,
            $this->serializer->denormalize(
                $event->getDetail(),
                EventInterface::class
            )
        );
    }
}
