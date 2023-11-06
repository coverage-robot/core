<?php

namespace App\Event;

use App\Client\DynamoDbClient;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreService;
use AsyncAws\DynamoDb\Exception\ConditionalCheckFailedException;
use Exception;
use Psr\Log\LoggerInterface;
use STS\Backoff\Backoff;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

abstract class AbstractOrchestratorEventRecorderProcessor implements OrchestratorEventProcessorInterface
{
    public function __construct(
        private readonly EventStoreService $eventStoreService,
        private readonly DynamoDbClient $dynamoDbClient,
        private readonly LoggerInterface $orchestratorEventRecorderProcessor
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
            useJitter: true
        );

        /**
         * @var bool $result
         */
        $result = $backoff->run(function () use ($newState) {
            try {
                $this->orchestratorEventRecorderProcessor->info(
                    sprintf(
                        'Attempting to record state change for %s in event store.',
                        (string)$newState
                    )
                );

                return $this->tryToRecordStateChangeInStore($newState);
            } catch (ConditionalCheckFailedException) {
                // Because of the strict versioning required for state change event sourcing,
                // its entirely possible that two contending
                $this->orchestratorEventRecorderProcessor->info(
                    sprintf(
                        'Conditional check for %s failed during persistence to the event store.',
                        (string)$newState
                    )
                );

                return false;
            }
        });

        return $result === true;
    }

    /**
     * Attempt a one-time persistence of a new state change into the event store.
     *
     * This is a best-effort implementation, as its possible this method will fail if the version number of the
     * state change collides with a newly persisted state change under the same version.
     *
     * @throws ConditionalCheckFailedException
     */
    private function tryToRecordStateChangeInStore(OrchestratedEventInterface $newState): bool
    {
        $stateChanges = $this->dynamoDbClient->getStateChangesForEvent($newState);

        $currentState = $this->reduceToOrchestratorEvent($stateChanges);

        $change = $this->eventStoreService->getStateChange(
            $currentState,
            $newState
        );

        if ($change === []) {
            $this->orchestratorEventRecorderProcessor->info(
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

        $this->orchestratorEventRecorderProcessor->info(
            sprintf(
                'Change detected in event state for %s, recording in event store.',
                (string)$newState
            ),
            [
                'event' => $newState::class,
                'stateChanges' => $stateChanges
            ]
        );

        return $this->dynamoDbClient->storeStateChange(
            $newState,
            count($stateChanges) + 1,
            $change
        );
    }

    /**
     * Reduce the state changes recorded in the event store down into the correct orchestrator event,
     * or fallback in the case the event is corrupt in the event store (i.e cannot be deserialized).
     */
    protected function reduceToOrchestratorEvent(array $stateChanges): ?OrchestratedEventInterface
    {
        if ($stateChanges === []) {
            return null;
        }

        try {
            return $this->eventStoreService->reduceStateChanges($stateChanges);
        } catch (ExceptionInterface $e) {
            $this->orchestratorEventRecorderProcessor->error(
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
