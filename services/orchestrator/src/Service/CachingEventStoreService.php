<?php

namespace App\Service;

use App\Model\EventStateChange;
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
         * @var WeakMap<EventStateChangeCollection, OrchestratedEventInterface> $cache
         */
        $cache = new WeakMap();

        $this->reducerCache = $cache;
    }

    public function getStateChangesBetweenEvent(
        ?OrchestratedEventInterface $currentState,
        OrchestratedEventInterface $newState
    ): array {
        return $this->eventStoreService->getStateChangesBetweenEvent($currentState, $newState);
    }

    public function reduceStateChangesToEvent(EventStateChangeCollection $stateChanges): OrchestratedEventInterface|null
    {
        if (isset($this->reducerCache[$stateChanges])) {
            return $this->reducerCache[$stateChanges];
        }

        $event = $this->eventStoreService->reduceStateChangesToEvent($stateChanges);

        if ($event instanceof OrchestratedEventInterface) {
            $this->reducerCache[$stateChanges] = $event;

            return $this->reducerCache[$stateChanges];
        }

        return $event;
    }

    public function storeStateChange(OrchestratedEventInterface $event): EventStateChange|false
    {
        return $this->eventStoreService->storeStateChange($event);
    }

    public function getAllStateChangesForCommit(string $repositoryIdentifier, string $commit): array
    {
        return $this->eventStoreService->getAllStateChangesForCommit($repositoryIdentifier, $commit);
    }

    public function getAllStateChangesForEvent(OrchestratedEventInterface $event): EventStateChangeCollection
    {
        return $this->eventStoreService->getAllStateChangesForEvent($event);
    }
}
