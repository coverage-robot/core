<?php

namespace App\Event;

use App\Client\DynamoDbClient;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

abstract class AbstractOrchestratorEventRecorderProcessor implements OrchestratorEventProcessorInterface
{
    public function __construct(
        private readonly EventStoreService $eventStoreService,
        private readonly DynamoDbClient $dynamoDbClient,
        private readonly LoggerInterface $orchestratorEventRecorderProcessor
    ) {
    }

    protected function recordStateChangeInStore(OrchestratedEventInterface $newState): bool
    {
        $stateChanges = $this->dynamoDbClient->getStateChangesByIdentifier($newState);

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
            count($stateChanges),
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
