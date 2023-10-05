<?php

namespace App\Handler;

use App\Service\Event\EventProcessorInterface;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class EventHandler extends EventBridgeHandler
{
    /**
     * @param EventProcessorInterface[] $eventProcessors
     */
    public function __construct(
        private readonly LoggerInterface $handlerLogger,
        #[TaggedIterator('app.event_processor', defaultIndexMethod: 'getProcessorEvent')]
        private readonly iterable $eventProcessors
    ) {
    }

    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        $eventType = CoverageEvent::from($event->getDetailType());

        $processor = (iterator_to_array($this->eventProcessors)[$eventType->value]) ?? null;

        if (!$processor instanceof EventProcessorInterface) {
            throw new RuntimeException(
                sprintf(
                    'No event processor for %s',
                    $eventType->value
                )
            );
        }

        $this->handlerLogger->info(
            sprintf(
                'Processing %s using %s.',
                $eventType->value,
                get_class($processor)
            )
        );

        $processor->process($event);
    }
}
