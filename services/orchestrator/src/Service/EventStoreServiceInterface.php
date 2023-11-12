<?php

namespace App\Service;

use App\Model\EventStateChangeCollection;
use App\Model\OrchestratedEventInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

interface EventStoreServiceInterface
{
    /**
     * Get the state change between our last known state, and the new
     * state of an event.
     */
    public function getStateChangeForEvent(
        ?OrchestratedEventInterface $currentState,
        OrchestratedEventInterface $newState
    ): array;

    /**
     * Reduce a pre-existing set of state changes into a single event, representing the current
     * state of the event.
     *
     * @throws ExceptionInterface
     */
    public function reduceStateChangesToEvent(EventStateChangeCollection $stateChanges): OrchestratedEventInterface;
}
