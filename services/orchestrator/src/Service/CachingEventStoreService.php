<?php

namespace App\Service;

use App\Model\EventStateChangeCollection;
use App\Model\OrchestratedEventInterface;
use WeakMap;

class CachingEventStoreService implements EventStoreServiceInterface
{
    /**
     * @var WeakMap<EventStateChangeCollection, OrchestratedEventInterface>
     */
    private WeakMap $reducerCache;

    public function __construct(
        private readonly EventStoreService $eventStoreService
    ) {
        /**
         * @var WeakMap<EventStateChangeCollection, OrchestratedEventInterface>
         */
        $this->reducerCache = new WeakMap();
    }

    public function getStateChangeForEvent(
        ?OrchestratedEventInterface $currentState,
        OrchestratedEventInterface $newState
    ): array {
        return $this->eventStoreService->getStateChangeForEvent($currentState, $newState);
    }

    public function reduceStateChangesToEvent(EventStateChangeCollection $stateChanges): OrchestratedEventInterface
    {
        if (isset($this->reducerCache[$stateChanges])) {
            return $this->reducerCache[$stateChanges];
        }

        $this->reducerCache[$stateChanges] = $this->eventStoreService->reduceStateChangesToEvent($stateChanges);

        return $this->reducerCache[$stateChanges];
    }
}
