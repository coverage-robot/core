<?php

namespace App\Event;

use App\Client\DynamoDbClient;
use App\Enum\OrchestratedEventState;
use App\Model\EventStateChangeCollection;
use App\Model\Finalised;
use App\Model\Ingestion;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreServiceInterface;
use AsyncAws\DynamoDb\Exception\ConditionalCheckFailedException;
use Psr\Log\LoggerInterface;
use STS\Backoff\Backoff;
use STS\Backoff\Strategies\PolynomialStrategy;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

trait OverallCommitStateAwareTrait
{
    /**
     * The state changes seen for the finalised event.
     *
     * This event is special, as it should (by convention) only exist once in each
     * commit.
     */
    private ?EventStateChangeCollection $finalisedEventStateChanges = null;

    public function __construct(
        private readonly EventStoreServiceInterface $eventStoreService,
        private readonly DynamoDbClient $dynamoDbClient,
        private readonly LoggerInterface $eventProcessorLogger
    ) {
    }

    /**
     * A helper method which looks at the event store and checks if there are any
     * events stored for the commit yet.
     */
    public function isNoEventsForCommit(OrchestratedEventInterface $newState): bool
    {
        $events = $this->dynamoDbClient->getEventStateChangesForCommit($newState);

        return count($events) == 0;
    }

    /**
     * A helper method which identifies if theres:
     * 1. Any uploads which have been uploaded but not published to the version control provider yet.
     * 2. Other ongoing events, meaning the coverage may not yet be ready.
     *
     * This method uses a polynomial backoff algorithm to retry checking the event
     * store, in the case subsequent events are being written to the event store soon
     * after.
     *
     * The retry interval is: 0ms, 150ms, 1200ms, 4050ms, 6000ms
     */
    public function isReadyToFinalise(OrchestratedEventInterface $newState): bool
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
            decider: static fn (
                int $attempt,
                int $maxAttempts,
                ?bool $result
            ) => ($attempt <= $maxAttempts) && $result == true
        );

        $previousTotalStateChanges = -1;

        /** @var bool $result */
        $result = $polling->run(function () use ($newState, &$previousTotalStateChanges) {
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

            if ($this->isAnOngoingEventPresent($newState, $mostRecentEventStateChanges)) {
                // At least one of the events is still ongoing, so we can stop polling
                return false;
            }

            if ($this->isAlreadyFinalised($newState, $mostRecentEventStateChanges)) {
                // All of the ingestion events have already been covered by a different
                // finalised event, so we can stop polling
                return false;
            }

            $previousTotalStateChanges = $currentTotalStateChanges;

            return true;
        });

        return $result;
    }

    /**
     * Record a new finalised event state into the event store.
     *
     * This method leverages the strong consistency of the event store (using the version number)
     * to effectively lock the finalised event from being published by different event which is
     * in contention.
     */
    public function recordFinalisedEvent(Finalised $newFinalisedState): bool
    {
        $currentFinalisedState = $this->finalisedEventStateChanges ?
            $this->eventStoreService->reduceStateChangesToEvent($this->finalisedEventStateChanges)
            : null;

        try {
            $this->dynamoDbClient->storeStateChange(
                $newFinalisedState,
                $this->finalisedEventStateChanges ?
                    count($this->finalisedEventStateChanges->getEvents()) + 1 :
                    1,
                $this->eventStoreService->getStateChangeForEvent(
                    $currentFinalisedState,
                    $newFinalisedState
                )
            );
        } catch (ConditionalCheckFailedException) {
            $this->eventProcessorLogger->info(
                'Finalised event has already been published, skipping.',
                [
                    'newState' => (string)$newFinalisedState,
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * Check if there are any events which are currently ongoing.
     *
     * If there is, it means there should be a new event coming after, and as such, we don't need
     * to poll.
     *
     * @param EventStateChangeCollection[] $collections
     */
    private function isAnOngoingEventPresent(
        OrchestratedEventInterface $newState,
        array $collections
    ): bool {
        foreach ($collections as $stateChanges) {
            try {
                $event = $this->eventStoreService->reduceStateChangesToEvent($stateChanges);

                if ($event->getState() === OrchestratedEventState::ONGOING) {
                    $this->eventProcessorLogger->info(
                        sprintf(
                            'Existing state is in ongoing state for %s.',
                            (string)$newState
                        ),
                        [
                            'ongoingEvent' => (string)$event,
                            'stateChanges' => $stateChanges->count()
                        ]
                    );

                    return true;
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

        return false;
    }

    /**
     * Check if all of the ingestion events occurred _before_ the last finalised event.
     *
     * This tells us whether theres reason to re-finalise the coverage, given that if theres
     * no more recent uploads, nothing in the results will have changed.
     *
     * @param EventStateChangeCollection[] $collections
     */
    private function isAlreadyFinalised(
        OrchestratedEventInterface $newState,
        array $collections
    ): bool {
        $lastIngestionTime = null;
        $finalisedTime = null;

        foreach ($collections as $stateChanges) {
            try {
                $event = $this->eventStoreService->reduceStateChangesToEvent($stateChanges);

                if ($event instanceof Ingestion) {
                    if ($event->getState() === OrchestratedEventState::ONGOING) {
                        continue;
                    }

                    if (
                        !$lastIngestionTime ||
                        $event->getEventTime() > $lastIngestionTime
                    ) {
                        $lastIngestionTime = $event->getEventTime();
                    }
                }

                if ($event instanceof Finalised) {
                    if (
                        !$finalisedTime ||
                        $event->getEventTime() > $finalisedTime
                    ) {
                        $finalisedTime = $event->getEventTime();
                        $this->finalisedEventStateChanges = $stateChanges;
                    }
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

        if (!$lastIngestionTime && !$finalisedTime) {
            // This is an edge case (but entirely possible) - theres been no uploads, and we haven't
            // written a finalised event yet. By convention, we _do_ want to finalise even if theres
            // been no uploads so we'll return false.
            $this->eventProcessorLogger->info(
                sprintf(
                    'Theres been no uploads, or finalised events for %s, so returning false.',
                    (string) $newState
                ),
                [
                    'lastIngestionTime' => $lastIngestionTime,
                    'finalisedTime' => $finalisedTime
                ]
            );
            return false;
        }

        return $finalisedTime && $lastIngestionTime < $finalisedTime;
    }
}
