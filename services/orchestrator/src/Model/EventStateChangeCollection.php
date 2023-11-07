<?php

namespace App\Model;

class EventStateChangeCollection implements \Countable
{
    /**
     * @var EventStateChange[] $events
     */
    private array $events;

    /**
     * @param EventStateChange[] $events
     */
    public function __construct(
        array $events
    ) {
        $this->events = array_reduce(
            $events,
            static fn(array $events, EventStateChange $event) => array_replace(
                $events,
                [$event->getVersion() => $event]
            ),
            []
        );
    }

    public function setStateChange(EventStateChange $stateChange): void
    {
        $this->events[$stateChange->getVersion()] = $stateChange;
    }

    /**
     * Get all of the state changes in the collection, in ascending version order.
     *
     * @return EventStateChange[]
     */
    public function getEvents(): array
    {
        ksort($this->events);
        return $this->events;
    }

    public function count(): int
    {
        return count($this->events);
    }
}
