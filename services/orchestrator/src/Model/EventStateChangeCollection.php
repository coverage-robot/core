<?php

namespace App\Model;

class EventStateChangeCollection
{
    /**
     * @param EventStateChange[] $stateChanges
     */
    public function __construct(
        private array $stateChanges
    ) {
    }

    public function setStateChange(EventStateChange $stateChange): void
    {
        $this->stateChanges[$stateChange->getVersion()] = $stateChange;
    }

    /**
     * Get all of the state changes in the collection, in ascending version order.
     *
     * @return EventStateChange[]
     */
    public function getStateChanges(): array
    {
        ksort($this->stateChanges);
        return $this->stateChanges;
    }
}
