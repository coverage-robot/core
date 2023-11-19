<?php

namespace App\Handler;

use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use Packages\Contracts\Event\Event;
use Packages\Event\Model\EventInterface;
use Packages\Event\Service\EventProcessorServiceInterface;
use Packages\Telemetry\TraceContext;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class EventHandler extends EventBridgeHandler
{
    /**
     * @param SerializerInterface&DenormalizerInterface&NormalizerInterface $serializer
     */
    public function __construct(
        private readonly EventProcessorServiceInterface $eventProcessorService,
        private readonly SerializerInterface $serializer
    ) {
    }

    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        TraceContext::setTraceHeaderFromContext($context);

        $this->eventProcessorService->process(
            Event::from($event->getDetailType()),
            $this->serializer->denormalize(
                $event->getDetail(),
                EventInterface::class
            )
        );
    }
}
