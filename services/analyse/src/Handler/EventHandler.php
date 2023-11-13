<?php

namespace App\Handler;

use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use Packages\Event\Enum\Event;
use Packages\Event\Handler\TraceContextAwareTrait;
use Packages\Event\Model\EventInterface;
use Packages\Event\Service\EventProcessorServiceInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class EventHandler extends EventBridgeHandler
{
    use TraceContextAwareTrait;

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
        $this->setTraceHeaderFromContext($context);

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
