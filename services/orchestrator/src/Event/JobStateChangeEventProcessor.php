<?php

namespace App\Event;

use App\Client\DynamoDbClient;
use App\Enum\OrchestratedEventState;
use App\Model\Job;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreService;
use Packages\Event\Enum\Event;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\JobStateChange;
use Packages\Models\Enum\JobState;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class JobStateChangeEventProcessor implements OrchestratorEventProcessorInterface
{
    public function __construct(
        private readonly EventStoreService $eventStoreService,
        private readonly DynamoDbClient $dynamoDbClient,
        private readonly LoggerInterface $ingestEventProcessorLogger
    ) {
    }

    public function process(EventInterface $event): bool
    {
        if (!$event instanceof JobStateChange) {
            $this->ingestEventProcessorLogger->critical(
                'Event is not intended to be processed by this processor',
                [
                    'event' => $event::class
                ]
            );
            return false;
        }

        $newState = new Job(
            $event->getProvider(),
            $event->getOwner(),
            $event->getRepository(),
            $event->getCommit(),
            match ($event->getState()) {
                JobState::IN_PROGRESS => OrchestratedEventState::ONGOING,
                default => OrchestratedEventState::SUCCESS
            },
            $event->getExternalId()
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

    public static function getEvent(): string
    {
        return Event::JOB_STATE_CHANGE->value;
    }
}
