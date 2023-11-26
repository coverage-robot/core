<?php

namespace App\Event;

use App\Enum\OrchestratedEventState;
use App\Event\Backoff\BackoffStrategyInterface;
use App\Event\Backoff\ReadyToFinaliseBackoffStrategy;
use App\Model\EventStateChangeCollection;
use App\Model\Finalised;
use App\Model\Ingestion;
use App\Model\OrchestratedEventInterface;
use App\Service\EventStoreService;
use App\Service\EventStoreServiceInterface;
use AsyncAws\DynamoDb\Exception\ConditionalCheckFailedException;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\Attribute\Required;

trait OverallCommitStateAwareTrait
{
    private BackoffStrategyInterface $backoffStrategy;

    public function __construct(
        #[Autowire(service: EventStoreService::class)]
        private readonly EventStoreServiceInterface $eventStoreService,
        private readonly LoggerInterface $eventProcessorLogger
    ) {
    }

    /**
     * A helper method which looks at the event store and checks if there are any
     * events stored for the commit yet.
     */
    public function isNoEventsForCommit(OrchestratedEventInterface $currentState): bool
    {
        $events = $this->eventStoreService->getAllStateChangesForCommit(
            $currentState->getUniqueRepositoryIdentifier(),
            $currentState->getCommit()
        );

        return count($events) == 0;
    }

    /**
     * A helper method which identifies if theres:
     * 1. Any uploads which have been uploaded but not published to the version control provider yet.
     * 2. Other ongoing events, meaning the coverage may not yet be ready.
     *
     * @see ReadyToFinaliseBackoffStrategy
     */
    public function isReadyToFinalise(OrchestratedEventInterface $currentState): bool
    {
        $this->eventProcessorLogger->info(
            sprintf(
                'Beginning polling for to see if all events remain finished in commit for %s.',
                (string)$currentState
            ),
        );

        $previousTotalStateChanges = -1;

        /** @var bool $result */
        $result = $this->backoffStrategy
            ->run(function () use ($currentState, &$previousTotalStateChanges) {
                $mostRecentEventStateChanges = $this->eventStoreService->getAllStateChangesForCommit(
                    $currentState->getUniqueRepositoryIdentifier(),
                    $currentState->getCommit()
                );

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
                            (string)$currentState
                        ),
                        [
                            'previousTotalStateChanges' => $previousTotalStateChanges,
                            'currentTotalStateChanges' => $currentTotalStateChanges
                        ]
                    );

                    return false;
                }

                if ($this->isAnOngoingEventPresent($currentState, $mostRecentEventStateChanges)) {
                    // At least one of the events is still ongoing, so we can stop polling
                    return false;
                }

                if ($this->isAlreadyFinalised($currentState, $mostRecentEventStateChanges)) {
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
        try {
            return $this->eventStoreService->storeStateChange($newFinalisedState) !== false;
        } catch (ConditionalCheckFailedException) {
            $this->eventProcessorLogger->info(
                'Finalised event has already been published, skipping.',
                [
                    'newState' => (string)$newFinalisedState,
                ]
            );

            return false;
        }
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
            $previousState = $this->eventStoreService->reduceStateChangesToEvent($stateChanges);

            if (!$previousState) {
                $this->eventProcessorLogger->warning(
                    'Unable to reduce state changes back into an event, skipping.',
                    [
                        'stateChanges' => $stateChanges
                    ]
                );

                continue;
            }

            if ($previousState->getState() === OrchestratedEventState::ONGOING) {
                $this->eventProcessorLogger->info(
                    sprintf(
                        'Existing state is in ongoing state for %s.',
                        (string)$newState
                    ),
                    [
                        'ongoingEvent' => (string)$previousState,
                        'stateChanges' => $stateChanges->count()
                    ]
                );

                return true;
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
            $previousState = $this->eventStoreService->reduceStateChangesToEvent($stateChanges);

            if (!$previousState) {
                $this->eventProcessorLogger->warning(
                    'Unable to reduce state changes back into an event, skipping.',
                    [
                        'stateChanges' => $stateChanges
                    ]
                );

                continue;
            }

            if ($previousState instanceof Ingestion) {
                if ($previousState->getState() === OrchestratedEventState::ONGOING) {
                    continue;
                }

                if (
                    !$lastIngestionTime ||
                    $previousState->getEventTime() > $lastIngestionTime
                ) {
                    $lastIngestionTime = $previousState->getEventTime();
                }
            }

            if (
                $previousState instanceof Finalised &&
                (!$finalisedTime || $previousState->getEventTime() > $finalisedTime)
            ) {
                $finalisedTime = $previousState->getEventTime();
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

        if ($finalisedTime && $lastIngestionTime > $finalisedTime) {
            /**
             * This indicates that at some point we've indirectly finalised the coverage results on a commit
             * **before** all of the coverage files were ingested. This is potentially an error, because it
             * could cause unexpected behaviour with annotations reporting lines as uncovered when in fact
             * they were covered by coverage we were yet to receive (and because they're are immutable, we
             * can't go back and delete annotations).
             *
             * We still need to publish results though, because the new coverage may have impacted the results
             * in such a way that the resulting data is different, and as such, leaving it out and dropping the
             * message could cause more harm than good.
             *
             * Equally, it may not be our fault at all (and something we couldn't guard against) - i.e. all jobs
             * finished a while ago but someone came back along and re-ran an old job much later which provided
             * us coverage we may or may not have had the first time.
             */
            $this->eventProcessorLogger->error(
                sprintf(
                    'New coverage has been ingested (%s) on a commit AFTER the results for the commit have' .
                    'already been finalised (%s).',
                    $lastIngestionTime->format(DateTimeInterface::ATOM),
                    $finalisedTime->format(DateTimeInterface::ATOM)
                ),
                [
                    'owner' => $newState->getOwner(),
                    'repository' => $newState->getRepository(),
                    'commit' => $newState->getCommit()
                ]
            );

            return false;
        }

        return $finalisedTime && $lastIngestionTime < $finalisedTime;
    }

    #[Required]
    public function withReadyToFinaliseBackoffStrategy(
        #[Autowire(service: ReadyToFinaliseBackoffStrategy::class)]
        BackoffStrategyInterface $backoffStrategy
    ): static {
        $this->backoffStrategy = $backoffStrategy;

        return $this;
    }
}
