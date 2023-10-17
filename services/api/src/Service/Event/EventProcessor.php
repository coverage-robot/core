<?php

namespace App\Service\Event;

use Bref\Event\EventBridge\EventBridgeEvent;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class EventProcessor
{
    /**
     * @param EventProcessorInterface[] $eventProcessors
     */
    public function __construct(
        #[TaggedIterator('app.event_processor', defaultIndexMethod: 'getProcessorEvent')]
        private readonly iterable $eventProcessors
    ) {
    }

    public function process(EventBridgeEvent $event): void
    {
        $processor = (iterator_to_array($this->eventProcessors)[$event->getDetailType()]) ?? null;

        if (!$processor instanceof EventProcessorInterface) {
            throw new RuntimeException(
                sprintf(
                    'No event processor for %s',
                    $event->getDetailType()
                )
            );
        }

        $processor->process($event);
    }
}
