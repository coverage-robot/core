<?php

namespace App\Handler;

use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use Handler\TraceContextAwareTrait;
use Packages\Event\Enum\Event;
use Packages\Event\Model\EventInterface;
use Packages\Event\Service\EventProcessorServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class EventHandler extends EventBridgeHandler
{
    use TraceContextAwareTrait;

    /**
     * @param SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer
     */
    public function __construct(
        private readonly LoggerInterface $eventHandlerLogger,
        private readonly EventProcessorServiceInterface $eventProcessor,
        private readonly SerializerInterface $serializer
    ) {
    }

    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        $this->setTraceHeaderFromContext($context);
        
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
