<?php

namespace App\Service;

use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\OrchestratedEventInterface;
use Override;
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

    #[Override]
    public function getStateChangesBetweenEvent(
        ?OrchestratedEventInterface $currentState,
        OrchestratedEventInterface $newState
    ): array {
        return $this->eventStoreService->getStateChangesBetweenEvent($currentState, $newState);
    }

    #[Override]
    public function reduceStateChangesToEvent(EventStateChangeCollection $stateChanges): OrchestratedEventInterface
    {
        if (isset($this->reducerCache[$stateChanges])) {
            return $this->reducerCache[$stateChanges];
        }

        $this->reducerCache[$stateChanges] = $this->eventStoreService->reduceStateChangesToEvent($stateChanges);

        return $this->reducerCache[$stateChanges];
    }

    #[Override]
    public function storeStateChange(OrchestratedEventInterface $event): EventStateChange|false
    {
        return $this->eventStoreService->storeStateChange($event);
    }

    #[Override]
    public function getAllStateChangesForCommit(string $repositoryIdentifier, string $commit): array
    {
        return $this->eventStoreService->getAllStateChangesForCommit($repositoryIdentifier, $commit);
    }

    #[Override]
    public function getAllStateChangesForEvent(OrchestratedEventInterface $event): EventStateChangeCollection
    {
        return $this->eventStoreService->getAllStateChangesForEvent($event);
    }
}
