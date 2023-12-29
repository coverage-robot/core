<?php

namespace App\Event;

use App\Enum\OrchestratedEventState;
use App\Event\Backoff\BackoffStrategyInterface;
use App\Event\Backoff\EventStoreRecorderBackoffStrategy;
use App\Model\Finalised;
use App\Model\Ingestion;
use App\Service\CachingEventStoreService;
use App\Service\EventStoreServiceInterface;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\EventSource;
use Packages\Event\Client\EventBusClient;
use Packages\Event\Model\IngestFailure;
use Packages\Event\Model\IngestStarted;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\UploadsFinalised;
use Packages\Event\Model\UploadsStarted;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

abstract class AbstractIngestEventProcessor extends AbstractOrchestratorEventRecorderProcessor
{
    use OverallCommitStateAwareTrait;

    public function __construct(
        #[Autowire(service: CachingEventStoreService::class)]
        private readonly EventStoreServiceInterface $eventStoreService,
        private readonly EventBusClient $eventBusClient,
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

    #[Override]
    public function process(EventInterface $event): bool
    {
        if (
            !$event instanceof IngestStarted &&
            !$event instanceof IngestSuccess &&
            !$event instanceof IngestFailure
        ) {
            $this->eventProcessorLogger->critical(
                'Event is not intended to be processed by this processor',
                [
                    'event' => $event
                ]
            );
            return false;
        }

        $currentState = new Ingestion(
            $event->getProvider(),
            $event->getOwner(),
            $event->getRepository(),
            $event->getCommit(),
            $event->getUploadId(),
            match (true) {
                $event instanceof IngestStarted => OrchestratedEventState::ONGOING,
                $event instanceof IngestSuccess => OrchestratedEventState::SUCCESS,
                $event instanceof IngestFailure => OrchestratedEventState::FAILURE
            },
            $event->getEventTime()
        );

        if ($this->isNoEventsForCommit($currentState)) {
            $this->eventProcessorLogger->info(
                'No events for commit, publishing event.',
                [
                    'event' => $event,
                    'newState' => $currentState,
                ]
            );

            $this->eventBusClient->fireEvent(
                EventSource::ORCHESTRATOR,
                new UploadsStarted(
                    $currentState->getProvider(),
                    $currentState->getOwner(),
                    $currentState->getRepository(),
                    $event->getRef(),
                    $currentState->getCommit(),
                    $event->getPullRequest(),
                    $event->getBaseRef(),
                    $event->getBaseCommit(),
                    new DateTimeImmutable()
                )
            );
        }

        $hasRecordedStateChange = $this->recordStateChangeInStore($currentState);

        if (
            $hasRecordedStateChange &&
            $currentState->getState() !== OrchestratedEventState::ONGOING &&
            $this->isReadyToFinalise($currentState)
        ) {
            $this->eventProcessorLogger->info(
                'All events are in a finished state, publishing event.',
                [
                    'event' => $event,
                    'newState' => $currentState,
                ]
            );

            $finalisedEvent = new Finalised(
                $currentState->getProvider(),
                $currentState->getOwner(),
                $currentState->getRepository(),
                $event->getRef(),
                $currentState->getCommit(),
                $event->getPullRequest(),
                new DateTimeImmutable()
            );

            if ($this->recordFinalisedEvent($finalisedEvent)) {
                $this->eventBusClient->fireEvent(
                    EventSource::ORCHESTRATOR,
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
}
