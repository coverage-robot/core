<?php

namespace Packages\Event\Service;

use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Processor\EventProcessorInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class EventProcessorService implements EventProcessorServiceInterface
{
    /**
     * @param EventProcessorInterface[] $eventProcessors
     */
    public function __construct(
        private readonly LoggerInterface $eventProcessorLogger,
        private readonly iterable $eventProcessors
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(Event $eventType, EventInterface $event): bool
    {
        $processor = (iterator_to_array($this->eventProcessors)[$eventType->value]) ?? null;

        if (!$processor instanceof EventProcessorInterface) {
            throw new RuntimeException(
                sprintf(
                    'No event processor for %s',
                    $eventType->value
                )
            );
        }

        $this->eventProcessorLogger->info(
            sprintf(
                'Processing %s using %s.',
                $eventType->value,
                get_class($processor)
            )
        );

        return $processor->process($event);
    }
}
