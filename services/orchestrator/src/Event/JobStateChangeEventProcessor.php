<?php

namespace App\Event;

use App\Client\EventBridgeEventClient;
use App\Enum\OrchestratedEventState;
use App\Event\Backoff\BackoffStrategyInterface;
use App\Event\Backoff\EventStoreRecorderBackoffStrategy;
use App\Model\Finalised;
use App\Model\Job;
use App\Service\CachingEventStoreService;
use App\Service\EventStoreServiceInterface;
use DateTimeImmutable;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Enum\JobState;
use Packages\Event\Model\JobStateChange;
use Packages\Event\Model\UploadsFinalised;
use Packages\Event\Model\UploadsStarted;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class JobStateChangeEventProcessor extends AbstractOrchestratorEventRecorderProcessor
{
    use OverallCommitStateAwareTrait;

    public function __construct(
        #[Autowire(service: CachingEventStoreService::class)]
        private readonly EventStoreServiceInterface $eventStoreService,
        private readonly EventBridgeEventClient $eventBridgeEventClient,
        private readonly LoggerInterface $eventProcessorLogger,
        #[Autowire(service: EventStoreRecorderBackoffStrategy::class)]
        private readonly BackoffStrategyInterface $eventStoreRecorderBackoffStrategy
    ) {
        parent::__construct(
            $eventStoreService,
            $eventProcessorLogger,
            $eventStoreRecorderBackoffStrategy
        );
    }

    public function process(EventInterface $event): bool
    {
        if (!$event instanceof JobStateChange) {
            $this->eventProcessorLogger->critical(
                'Event is not intended to be processed by this processor',
                [
                    'event' => $event
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
                JobState::COMPLETED => OrchestratedEventState::SUCCESS,
                default => OrchestratedEventState::ONGOING
            },
            $event->getEventTime(),
            $event->getExternalId()
        );

        if ($this->isNoEventsForCommit($newState)) {
            $this->eventProcessorLogger->info(
                'No events for commit, publishing event.',
                [
                    'event' => $event,
                    'newState' => $newState,
                ]
            );

            $this->eventBridgeEventClient->publishEvent(
                new UploadsStarted(
                    $newState->getProvider(),
                    $newState->getOwner(),
                    $newState->getRepository(),
                    $event->getRef(),
                    $newState->getCommit(),
                    $event->getPullRequest(),
                    $event->getBaseRef(),
                    $event->getBaseCommit(),
                    new DateTimeImmutable()
                )
            );
        }

        $hasRecordedStateChange = $this->recordStateChangeInStore($newState);

        if (
            $hasRecordedStateChange &&
            $newState->getState() === OrchestratedEventState::SUCCESS &&
            $this->isReadyToFinalise($newState)
        ) {
            $this->eventProcessorLogger->info(
                'All events are in a finished state, publishing event.',
                [
                    'event' => $event,
                    'newState' => $newState,
                ]
            );

            $finalisedEvent = new Finalised(
                $newState->getProvider(),
                $newState->getOwner(),
                $newState->getRepository(),
                $event->getRef(),
                $newState->getCommit(),
                $event->getPullRequest(),
                new DateTimeImmutable()
            );

            if ($this->recordFinalisedEvent($finalisedEvent)) {
                $this->eventBridgeEventClient->publishEvent(
                    new UploadsFinalised(
                        $finalisedEvent->getProvider(),
                        $finalisedEvent->getOwner(),
                        $finalisedEvent->getRepository(),
                        $finalisedEvent->getRef(),
                        $finalisedEvent->getCommit(),
                        $event->getParent(),
                        $finalisedEvent->getPullRequest(),
                        $event->getBaseCommit(),
                        $event->getBaseRef(),
                        $finalisedEvent->getEventTime()
                    )
                );
            }
        }

        return $hasRecordedStateChange;
    }

    public static function getEvent(): string
    {
        return Event::JOB_STATE_CHANGE->value;
    }
}
