<?php

namespace Packages\Event\Service;

use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Processor\EventProcessorInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class EventProcessorService implements EventProcessorServiceInterface
{
    /**
     * @param array<value-of<Event>, EventProcessorInterface> $eventProcessors
     */
    public function __construct(
        private readonly LoggerInterface $eventProcessorLogger,
        #[TaggedIterator('event.processor', defaultIndexMethod: 'getEvent')]
        private readonly iterable $eventProcessors
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(Event $eventType, EventInterface $event): bool
    {
        $processor = ($this->getRegisteredProcessors()[$eventType->value]) ?? null;

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

    /**
     * Get a list of all the processors which are registered and available to handle events.
     *
     * @return array<value-of<Event>, EventProcessorInterface>
     */
    public function getRegisteredProcessors(): array
    {
        return iterator_to_array($this->eventProcessors);
    }
}
