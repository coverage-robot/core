<?php

namespace App\Event;

use App\Enum\EnvironmentVariable;
use App\Enum\OrchestratedEventState;
use App\Event\Backoff\BackoffStrategyInterface;
use App\Event\Backoff\EventStoreRecorderBackoffStrategy;
use App\Model\Finalised;
use App\Model\Job;
use App\Service\CachingEventStoreService;
use App\Service\EventStoreServiceInterface;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\EventSource;
use Packages\Event\Client\EventBusClient;
use Packages\Event\Client\EventBusClientInterface;
use Packages\Event\Enum\JobState;
use Packages\Event\Model\JobStateChange;
use Packages\Event\Model\UploadsFinalised;
use Packages\Event\Model\UploadsStarted;
use Packages\Message\Client\PublishClient;
use Packages\Message\Client\SqsClientInterface;
use Packages\Message\PublishableMessage\PublishableCoverageRunningJobMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class JobStateChangeEventProcessor extends AbstractOrchestratorEventRecorderProcessor
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
        private readonly EnvironmentServiceInterface $environmentService,
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
        if (!$event instanceof JobStateChange) {
            $this->eventProcessorLogger->critical(
                'Event is not intended to be processed by this processor',
                [
                    'event' => $event
                ]
            );
            return false;
        }

        if (
            $event->getTriggeredByExternalId() ===
                $this->environmentService->getVariable(EnvironmentVariable::GITHUB_APP_ID)
        ) {
            $this->eventProcessorLogger->info(
                sprintf(
                    'Ignoring event as the state change was caused by us: %s',
                    (string)$event
                )
            );

            return true;
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

            $this->eventBusClient->fireEvent(
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
                OrchestratedEventState::ONGOING,
                $event->getPullRequest(),
                new DateTimeImmutable()
            );

            if ($this->recordFinalisedEvent($finalisedEvent)) {
                $this->publishClient->dispatch(new PublishableCoverageRunningJobMessage($event));

                $this->eventBusClient->fireEvent(
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

    #[Override]
    public static function getEvent(): string
    {
        return Event::JOB_STATE_CHANGE->value;
    }
}
