<?php

namespace App\Event;

use App\Client\DynamoDbClient;
use App\Client\EventBridgeEventClient;
use App\Enum\OrchestratedEventState;
use App\Model\Finalised;
use App\Model\Job;
use App\Service\EventStoreServiceInterface;
use DateTimeImmutable;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Model\JobStateChange;
use Packages\Event\Model\UploadsFinalised;
use Packages\Event\Model\UploadsStarted;
use Packages\Models\Enum\JobState;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class JobStateChangeEventProcessor extends AbstractOrchestratorEventRecorderProcessor
{
    use OverallCommitStateAwareTrait;

    public function __construct(
        #[Autowire(service: 'App\Service\CachingEventStoreService')]
        private readonly EventStoreServiceInterface $eventStoreService,
        private readonly DynamoDbClient $dynamoDbClient,
        private readonly EventBridgeEventClient $eventBridgeEventClient,
        private readonly LoggerInterface $eventProcessorLogger
    ) {
        parent::__construct(
            $eventStoreService,
            $dynamoDbClient,
            $eventProcessorLogger
        );
    }

    public function process(EventInterface $event): bool
    {
        if (!$event instanceof JobStateChange) {
            $this->eventProcessorLogger->critical(
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
                    'event' => (string)$event,
                    'newState' => (string)$newState,
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
                    'event' => (string)$event,
                    'newState' => (string)$newState,
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
                        $finalisedEvent->getPullRequest(),
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
