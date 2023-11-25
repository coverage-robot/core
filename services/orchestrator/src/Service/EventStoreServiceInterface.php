<?php

namespace App\Service;

use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\OrchestratedEventInterface;

interface EventStoreServiceInterface
{
    /**
     * Get the state change between our last known state, and the new
     * state of an event.
     */
    public function getStateChangesBetweenEvent(
        ?OrchestratedEventInterface $currentState,
        OrchestratedEventInterface $newState
    ): array;

    /**
     * Reduce a pre-existing set of state changes into a single event, representing the current
     * state of the event.
     *
     * If the event is in some way corrupt, this method will return null which represents no valid state.
     */
    public function reduceStateChangesToEvent(
        EventStateChangeCollection $stateChanges
    ): OrchestratedEventInterface|null;

    /**
     * Store any state changes which have occurred between the current state, and whatever the event store believed
     * the state of the event to be in prior to this call.
     */
    public function storeStateChange(OrchestratedEventInterface $event): EventStateChange|false;

    /**
     * Get a collection of all the state changes which have occurred for a given commit.
     *
     * @return EventStateChangeCollection[]
     */
    public function getAllStateChangesForCommit(string $repositoryIdentifier, string $commit): array;

    /**
     * Get a collection of all the state changes which have occurred for a given event.
     *
     * This effectively returns a history of all the changes which have occurred for a given event, which can be used
     * to reconstruct an event at any point in time.
     */
    public function getAllStateChangesForEvent(OrchestratedEventInterface $event): EventStateChangeCollection;
}
