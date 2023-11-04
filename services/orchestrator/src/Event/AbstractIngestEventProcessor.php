<?php

namespace App\Event;

use App\Client\DynamoDbClient;
use App\Enum\OrchestratedEventState;
use App\Model\Ingestion;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreService;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\IngestFailure;
use Packages\Event\Model\IngestSuccess;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

abstract class AbstractIngestEventProcessor implements OrchestratorEventProcessorInterface
{
    public function __construct(
        private readonly EventStoreService $eventStoreService,
        private readonly DynamoDbClient $dynamoDbClient,
        private readonly LoggerInterface $ingestEventProcessorLogger
    ) {
    }

    public function process(EventInterface $event): bool
    {
        if (
            !$event instanceof IngestSuccess &&
            !$event instanceof IngestFailure
        ) {
            $this->ingestEventProcessorLogger->critical(
                'Event is not intended to be processed by this processor',
                [
                    'event' => $event::class
                ]
            );
            return false;
        }

        $newState = new Ingestion(
            $event->getProvider(),
            $event->getOwner(),
            $event->getRepository(),
            $event->getCommit(),
            match (true) {
                $event instanceof IngestSuccess => OrchestratedEventState::SUCCESS,
                $event instanceof IngestFailure => OrchestratedEventState::FAILURE
            }
        );

        $stateChanges = $this->dynamoDbClient->getStateChangesByIdentifier($newState->getIdentifier());

        $currentState = $this->reduceToOrchestratorEvent($stateChanges);

        $change = $this->eventStoreService->getStateChange(
            $currentState,
            $newState
        );

        $this->dynamoDbClient->storeEventChange(
            $newState,
            count($stateChanges),
            $change
        );

        return true;
    }

    private function reduceToOrchestratorEvent(array $stateChanges): ?OrchestratedEventInterface
    {
        try {
            return $this->eventStoreService->reduceStateChanges($stateChanges);
        } catch (ExceptionInterface $e) {
            $this->ingestEventProcessorLogger->error(
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
