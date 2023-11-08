<?php

namespace App\Event;

use App\Client\DynamoDbClient;
use App\Enum\OrchestratedEventState;
use App\Model\EventStateChangeCollection;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreService;
use Psr\Log\LoggerInterface;
use STS\Backoff\Backoff;
use STS\Backoff\Strategies\PolynomialStrategy;
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
     *
     * This method uses a polynomial backoff algorithm to retry checking the event
     * store, in the case subsequent events are being written to the event store soon
     * after.
     *
     * The retry interval is: 0ms, 150ms, 1200ms, 4050ms, 6000ms
     */
    public function areAllEventsForCommitInFinishedState(OrchestratedEventInterface $newState): bool
    {
        $this->eventProcessorLogger->info(
           sprintf(
               'Beginning polling for to see if all events remain finished in commit for %s.',
               (string)$newState
           ),
        );

        $polling = new Backoff(
            maxAttempts: 4,
            strategy: new PolynomialStrategy(150, 3),
            waitCap: 6000,
            decider: static fn ($attempt, $maxAttempts, $result) => ($attempt <= $maxAttempts) && $result == true
        );

        $previousTotalStateChanges = -1;

        return $polling->run(function () use ($newState, &$previousTotalStateChanges) {
            $mostRecentEventStateChanges = $this->dynamoDbClient->getEventStateChangesForCommit($newState);

            $currentTotalStateChanges = array_sum(
                array_map(
                    static fn(EventStateChangeCollection $stateChanges) => count($stateChanges->getEvents()),
                    $mostRecentEventStateChanges
                )
            );

            if (
                $previousTotalStateChanges >= 0 &&
                $currentTotalStateChanges > $previousTotalStateChanges
            ) {
                // Theres new state events since we last checked, so we should stop polling
                // as a new event will have been processed and will have assessed our state
                // as finished (i.e. we aren't needed anymore)
                $this->eventProcessorLogger->info(
                    sprintf(
                        'New state events have been recorded since last check, stopping polling on %s.',
                        (string)$newState
                    ),
                    [
                        'previousTotalStateChanges' => $previousTotalStateChanges,
                        'currentTotalStateChanges' => $currentTotalStateChanges
                    ]
                );
                return false;
            }

            foreach ($mostRecentEventStateChanges as $stateChanges) {
                try {
                    $event = $this->eventStoreService->reduceStateChangesToEvent($stateChanges);

                    if ($event->getState() === OrchestratedEventState::ONGOING) {
                        // At least one of the events is still ongoing, so we can stop polling
                        $this->eventProcessorLogger->info(
                            sprintf(
                                'Existing state is in ongoing state, stopping polling for %s.',
                                (string)$newState
                            ),
                            [
                                'previousTotalStateChanges' => $previousTotalStateChanges,
                                'currentTotalStateChanges' => $currentTotalStateChanges
                            ]
                        );
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

            $previousTotalStateChanges = $currentTotalStateChanges;

            return true;
        });
    }
}
