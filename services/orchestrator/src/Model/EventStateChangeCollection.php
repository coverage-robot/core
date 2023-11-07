<?php

namespace App\Model;

class EventStateChangeCollection implements \Countable
{
    /**
     * @param EventStateChange[] $events
     */
    public function __construct(
        private array $events
    ) {
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
