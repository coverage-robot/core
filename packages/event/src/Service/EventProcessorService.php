<?php

declare(strict_types=1);

namespace Packages\Event\Service;

use Override;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Processor\EventProcessorInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class EventProcessorService implements EventProcessorServiceInterface
{
    /**
     * @param array<value-of<Event>, EventProcessorInterface> $eventProcessors
     */
    public function __construct(
        private LoggerInterface $eventProcessorLogger,
        #[AutowireIterator('event.processor', defaultIndexMethod: 'getEvent')]
        private iterable $eventProcessors
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
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
                $processor::class
            )
        );

        return $processor->process($event);
    }

    /**
     * Get a list of all the processors which are registered and available to handle events.
     *
     * @return array<array-key, mixed>
     */
    public function getRegisteredProcessors(): array
    {
        return iterator_to_array($this->eventProcessors);
    }
}
