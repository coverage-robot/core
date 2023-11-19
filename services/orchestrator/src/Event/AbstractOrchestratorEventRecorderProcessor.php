<?php

namespace App\Event;

use App\Client\DynamoDbClient;
use App\Exception\OutOfOrderEventException;
use App\Model\EventStateChangeCollection;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreServiceInterface;
use AsyncAws\DynamoDb\Exception\ConditionalCheckFailedException;
use Exception;
use Psr\Log\LoggerInterface;
use STS\Backoff\Backoff;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

abstract class AbstractOrchestratorEventRecorderProcessor implements EventProcessorInterface
{
    public function __construct(
        private readonly EventStoreServiceInterface $eventStoreService,
        private readonly DynamoDbClient $dynamoDbClient,
        private readonly LoggerInterface $eventProcessorLogger
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
    protected function recordStateChangeInStore(OrchestratedEventInterface $newState): bool
    {
        $backoff = new Backoff(
            maxAttempts: 3,
            useJitter: true,
            decider: function (
                int $attempt,
                int $maxAttempts,
                ?bool $result,
                ?Exception $exception = null
            ) use ($newState) {
                if ($exception instanceof OutOfOrderEventException) {
                    // Theres no point in re-trying this, as the event is out of order (i.e.
                    // a newer event has already been recorded)
                    return false;
                }

                return ($attempt <= $maxAttempts) && (!$result || $exception);
            }
        );

        /**
         * @var bool|null $result
         */
        $result = $backoff->run(function () use ($newState) {
            $this->eventProcessorLogger->info(
                sprintf(
                    'Attempting to record state change for %s in event store.',
                    (string)$newState
                )
            );

            return $this->tryToRecordStateChangeInStore($newState);
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
    private function tryToRecordStateChangeInStore(OrchestratedEventInterface $newState): bool
    {
        $stateChanges = $this->dynamoDbClient->getStateChangesForEvent($newState);

        $currentState = $this->reduceToOrchestratorEvent($stateChanges);

        if (
            $currentState &&
            $currentState->getEventTime() > $newState->getEventTime()
        ) {
            $this->eventProcessorLogger->info(
                sprintf(
                    'Dropping change change for %s as it is older than the current state.',
                    (string)$newState
                ),
                [
                    'current' => $currentState->getEventTime(),
                    'new' => $newState->getEventTime()
                ]
            );

            throw new OutOfOrderEventException('Event is older than the current state.');
        }

        $change = $this->eventStoreService->getStateChangeForEvent(
            $currentState,
            $newState
        );

        if ($change === []) {
            $this->eventProcessorLogger->info(
                sprintf(
                    'No change detected in event state for %s.',
                    (string)$newState
                ),
                [
                    'event' => $newState::class,
                    'stateChanges' => $stateChanges
                ]
            );

            return true;
        }

        $this->eventProcessorLogger->info(
            sprintf(
                'Change detected in event state for %s, recording in event store.',
                (string)$newState
            ),
            [
                'event' => $newState::class,
                'stateChanges' => $stateChanges
            ]
        );

        try {
            return $this->dynamoDbClient->storeStateChange(
                $newState,
                count($stateChanges) + 1,
                $change
            );
        } catch (ConditionalCheckFailedException) {
            // An event has already been recorded with the version number we're attempting to
            // use. That means we haven't factored in the latest state of the event, so we should
            // retry.
            $this->eventProcessorLogger->info(
                sprintf(
                    'Conditional check for %s failed during persistence to the event store.',
                    (string)$newState
                )
            );

            return false;
        }
    }

    /**
     * Reduce the state changes recorded in the event store down into the correct orchestrator event,
     * or fallback in the case the event is corrupt in the event store (i.e cannot be deserialized).
     */
    protected function reduceToOrchestratorEvent(EventStateChangeCollection $stateChanges): ?OrchestratedEventInterface
    {
        if ($stateChanges->getEvents() === []) {
            return null;
        }

        try {
            return $this->eventStoreService->reduceStateChangesToEvent($stateChanges);
        } catch (ExceptionInterface $e) {
            $this->eventProcessorLogger->error(
                'Failed to reduce state changes into event.',
                [
                    'stateChanges' => $stateChanges,
                    'exception' => $e
                ]
            );

            return null;
        }
    }
}
