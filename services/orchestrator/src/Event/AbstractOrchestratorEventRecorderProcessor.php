<?php

namespace App\Event;

use App\Enum\OrchestratedEventState;
use App\Event\Backoff\BackoffStrategyInterface;
use App\Exception\OutOfOrderEventException;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreServiceInterface;
use AsyncAws\DynamoDb\Exception\ConditionalCheckFailedException;
use Exception;
use Packages\Event\Processor\EventProcessorInterface;
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
            ->run(function () use ($previousState): bool {
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
            $previousState instanceof OrchestratedEventInterface &&
            $previousState->getEventTime() > $currentState->getEventTime()
        ) {
            $this->eventProcessorLogger->info(
                sprintf(
                    'Dropping change for %s as it is older than the current state.',
                    (string)$currentState
                ),
                [
                    'current' => $previousState,
                    'new' => $currentState
                ]
            );

            throw new OutOfOrderEventException('Event is older than the current state.');
        }

        if (
            $previousState instanceof OrchestratedEventInterface &&
            in_array($previousState->getState(), [OrchestratedEventState::SUCCESS, OrchestratedEventState::FAILURE]) &&
            $currentState->getState() === OrchestratedEventState::ONGOING
        ) {
            /**
             * This helps to catch potentially out-of-order events, where an event is moved from a finished
             * state (i.e. success or failure) to an ongoing state.
             *
             * This is, most likely a webhook which was delayed from the provider, and is now being processed
             * out of order (i.e. the current state is newer).
             *
             * If we recorded this event in the store, we'd likely wind up with a permanently ongoing
             * event, which would prevent the job from finishing.
             *
             * What you'll likely find is the event time of both events is **exactly** the same (to the
             * millisecond), which is why it wasn't caught by the previous check (a more reliable check).
             */
            $this->eventProcessorLogger->info(
                sprintf(
                    'Dropping change for %s as it moves the events state from a finished state to an ongoing state.',
                    (string)$currentState
                ),
                [
                    'current' => $previousState,
                    'new' => $currentState,
                ]
            );

            throw new OutOfOrderEventException('Event is an earlier state type than that of the current event state.');
        }

        $this->eventProcessorLogger->info(
            sprintf(
                'Change detected in event state for %s, recording in event store.',
                (string)$currentState
            ),
            [
                'event' => $currentState,
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
