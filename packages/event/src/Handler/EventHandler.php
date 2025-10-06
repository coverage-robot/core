<?php

declare(strict_types=1);

namespace Packages\Event\Handler;

use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Event\Model\EventInterface;
use Packages\Event\Service\EventProcessorService;
use Packages\Event\Service\EventProcessorServiceInterface;
use Packages\Event\Service\EventValidationService;
use Packages\Telemetry\Service\TraceContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class EventHandler extends EventBridgeHandler
{
    public function __construct(
        #[Autowire(service: EventProcessorService::class)]
        private readonly EventProcessorServiceInterface $eventProcessorService,
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly EventValidationService $eventValidationService
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Override]
    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        TraceContext::setTraceHeaderFromContext($context);

        $eventType = Event::from($event->getDetailType());

        /** @var EventInterface $eventModel */
        $eventModel = $this->serializer->denormalize(
            $event->getDetail(),
            EventInterface::class
        );

        $this->eventValidationService->validate($eventModel);

        $this->eventProcessorService->process(
            $eventType,
            $eventModel
        );
    }
}
