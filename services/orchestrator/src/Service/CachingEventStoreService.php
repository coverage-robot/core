<?php

namespace App\Service;

use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\OrchestratedEventInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use WeakMap;

final class CachingEventStoreService implements EventStoreServiceInterface
{
    /**
     * @var WeakMap<EventStateChangeCollection, OrchestratedEventInterface>
     */
    private WeakMap $reducerCache;

    public function __construct(
        #[Autowire(service: EventStoreService::class)]
        private readonly EventStoreServiceInterface $eventStoreService
    ) {
        /**
         * @var WeakMap<EventStateChangeCollection, OrchestratedEventInterface> $cache
         */
        $cache = new WeakMap();

        $this->reducerCache = $cache;
    }

    #[Override]
    public function getStateChangesBetweenEvent(
        ?OrchestratedEventInterface $currentState,
        OrchestratedEventInterface $newState
    ): array {
        return $this->eventStoreService->getStateChangesBetweenEvent($currentState, $newState);
    }

    #[Override]
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
