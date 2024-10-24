<?php

declare(strict_types=1);

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
use Packages\Event\Client\EventBusClient;
use Packages\Event\Client\EventBusClientInterface;
use Packages\Event\Model\IngestFailure;
use Packages\Event\Model\IngestStarted;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\UploadsFinalised;
use Packages\Event\Model\UploadsStarted;
use Packages\Message\Client\PublishClient;
use Packages\Message\Client\SqsClientInterface;
use Packages\Message\PublishableMessage\PublishableCoverageRunningJobMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

abstract class AbstractIngestEventProcessor extends AbstractOrchestratorEventRecorderProcessor
{
    use OverallCommitStateAwareTrait;

    public function __construct(
        #[Autowire(service: CachingEventStoreService::class)]
        private readonly EventStoreServiceInterface $eventStoreService,
        #[Autowire(service: EventBusClient::class)]
        private readonly EventBusClientInterface $eventBusClient,
        private readonly LoggerInterface $eventProcessorLogger,
        #[Autowire(service: EventStoreRecorderBackoffStrategy::class)]
        private readonly BackoffStrategyInterface $eventStoreRecorderBackoffStrategy,
        #[Autowire(service: PublishClient::class)]
        private readonly SqsClientInterface $publishClient
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
            (string)$event->getProjectId(),
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
                new UploadsStarted(
                    provider: $currentState->getProvider(),
                    projectId: $currentState->getProjectId(),
                    owner: $currentState->getOwner(),
                    repository: $currentState->getRepository(),
                    ref: $event->getRef(),
                    commit: $currentState->getCommit(),
                    pullRequest: $event->getPullRequest(),
                    baseRef: $event->getBaseRef(),
                    baseCommit: $event->getBaseCommit(),
                    eventTime: new DateTimeImmutable()
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
                $currentState->getProjectId(),
                $currentState->getOwner(),
                $currentState->getRepository(),
                $event->getRef(),
                $currentState->getCommit(),
                OrchestratedEventState::ONGOING,
                $event->getPullRequest(),
                new DateTimeImmutable()
            );

            if ($this->recordFinalisedEvent($finalisedEvent)) {
                $this->publishClient->dispatch(new PublishableCoverageRunningJobMessage($event));

                $this->eventBusClient->fireEvent(
                    new UploadsFinalised(
                        provider: $finalisedEvent->getProvider(),
                        projectId: $finalisedEvent->getProjectId(),
                        owner: $finalisedEvent->getOwner(),
                        repository: $finalisedEvent->getRepository(),
                        ref: $finalisedEvent->getRef(),
                        commit: $finalisedEvent->getCommit(),
                        parent: $event->getParent(),
                        pullRequest: $finalisedEvent->getPullRequest(),
                        baseCommit: $event->getBaseCommit(),
                        baseRef: $event->getBaseRef(),
                        eventTime: $finalisedEvent->getEventTime()
                    )
                );
            }
        }

        return $hasRecordedStateChange;
    }
}
