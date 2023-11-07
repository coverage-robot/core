<?php

namespace App\Event;

use App\Client\DynamoDbClient;
use App\Enum\OrchestratedEventState;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

trait OverallCommitStateAwareTrait
{
    public function __construct(
        private readonly EventStoreService $eventStoreService,
        private readonly DynamoDbClient $dynamoDbClient,
        private readonly LoggerInterface $eventProcessorLogger
    ) {
    }

    /**
     * A helper method which looks at the event store and checks if there are any
     * events stored for the commit yet.
     */
    public function areNoEventsForCommit(OrchestratedEventInterface $newState): bool
    {
        $events = $this->dynamoDbClient->getEventStateChangesForCommit($newState);

        return count($events) == 0;
    }

    /**
     * A helper method which looks at the event store and checks if all of the
     * events are in a finished state for a given commit in a repository.
     */
    public function areAllEventsForCommitInFinishedState(OrchestratedEventInterface $newState): bool
    {
        $events = $this->dynamoDbClient->getEventStateChangesForCommit($newState);

        foreach ($events as $stateChanges) {
            try {
                $event = $this->eventStoreService->reduceStateChangesToEvent($stateChanges);

                if ($event->getState() === OrchestratedEventState::ONGOING) {
                    return false;
                }
            } catch (ExceptionInterface $e) {
                $this->eventProcessorLogger->warning(
                    'Unable to reduce state changes back into an event, skipping.',
                    [
                        'stateChanges' => $stateChanges,
                        'exception' => $e
                    ]
                );

                continue;
            }
        }

        return true;
    }
}
