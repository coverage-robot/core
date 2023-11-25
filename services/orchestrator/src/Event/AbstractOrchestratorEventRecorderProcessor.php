<?php

namespace App\Event;

use App\Event\Backoff\BackoffStrategyInterface;
use App\Exception\OutOfOrderEventException;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreServiceInterface;
use AsyncAws\DynamoDb\Exception\ConditionalCheckFailedException;
use Exception;
use Psr\Log\LoggerInterface;

abstract class AbstractOrchestratorEventRecorderProcessor implements EventProcessorInterface
{
    public function __construct(
        private readonly EventStoreServiceInterface $eventStoreService,
        private readonly LoggerInterface $eventProcessorLogger,
        private readonly BackoffStrategyInterface $backoffStrategy
    ) {
    }

    /**
     * Record a state change in the event store, using the desired state, and the existing changes
     * already present in the event store.
     *
     * This method uses a backoff algorithm to retry the state change in the event store, in the case
     * that the version number of the state change collides with a competing state change which occurred
     * at the same time.
     *
     * @throws Exception
     */
    protected function recordStateChangeInStore(OrchestratedEventInterface $previousState): bool
    {
        /**
         * @var bool|null $result
         */
        $result = $this->backoffStrategy
            ->run(function () use ($previousState) {
                $this->eventProcessorLogger->info(
                    sprintf(
                        'Attempting to record state change for %s in event store.',
                        (string)$previousState
                    )
                );

                return $this->tryToRecordStateChangeInStore($previousState);
            });

        return $result === true;
    }

    /**
     * Attempt a one-time persistence of a new state change into the event store.
     *
     * This is a best-effort implementation, as its possible this method will fail if the version number of the
     * state change collides with a newly persisted state change under the same version.
     *
     * Equally, this method will drop the state change if it is older than the current state, which handles
     * scenarios with out-of-order events or ones in contention.
     *
     * @throws ConditionalCheckFailedException
     * @throws OutOfOrderEventException
     */
    private function tryToRecordStateChangeInStore(OrchestratedEventInterface $currentState): bool
    {
        $stateChanges = $this->eventStoreService->getAllStateChangesForEvent($currentState);

        $previousState = $this->eventStoreService->reduceStateChangesToEvent($stateChanges);

        if (
            $previousState &&
            $previousState->getEventTime() > $currentState->getEventTime()
        ) {
            $this->eventProcessorLogger->info(
                sprintf(
                    'Dropping change for %s as it is older than the current state.',
                    (string)$currentState
                ),
                [
                    'current' => $previousState->getEventTime(),
                    'new' => $currentState->getEventTime()
                ]
            );

            throw new OutOfOrderEventException('Event is older than the current state.');
        }

        $this->eventProcessorLogger->info(
            sprintf(
                'Change detected in event state for %s, recording in event store.',
                (string)$currentState
            ),
            [
                'event' => $currentState::class,
                'stateChanges' => $stateChanges
            ]
        );

        try {
            return $this->eventStoreService->storeStateChange($currentState) !== false;
        } catch (ConditionalCheckFailedException) {
            // An event has already been recorded with the version number we're attempting to
            // use. That means we haven't factored in the latest state of the event, so we should
            // retry.
            $this->eventProcessorLogger->info(
                sprintf(
                    'Conditional check for %s failed during persistence to the event store.',
                    (string)$currentState
                )
            );

            return false;
        }
    }
}
