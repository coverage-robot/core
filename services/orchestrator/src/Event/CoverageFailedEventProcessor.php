<?php

namespace App\Event;

use App\Enum\OrchestratedEventState;
use App\Event\Backoff\BackoffStrategyInterface;
use App\Event\Backoff\EventStoreRecorderBackoffStrategy;
use App\Model\Finalised;
use App\Service\CachingEventStoreService;
use App\Service\EventStoreServiceInterface;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Message\PublishableMessage\PublishableCoverageFailedJobMessage;
use Packages\Event\Model\CoverageFailed;
use Packages\Message\Client\PublishClient;
use Packages\Message\Client\SqsClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CoverageFailedEventProcessor extends AbstractOrchestratorEventRecorderProcessor
{
    use OverallCommitStateAwareTrait;

    public function __construct(
        #[Autowire(service: CachingEventStoreService::class)]
        private readonly EventStoreServiceInterface $eventStoreService,
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
        if (!$event instanceof CoverageFailed) {
            $this->eventProcessorLogger->critical(
                'Event is not intended to be processed by this processor',
                [
                    'event' => $event
                ]
            );
            return false;
        }

        $finalisedEvent = new Finalised(
            $event->getProvider(),
            $event->getOwner(),
            $event->getRepository(),
            $event->getRef(),
            $event->getCommit(),
            OrchestratedEventState::FAILURE,
            $event->getPullRequest(),
            new DateTimeImmutable()
        );

        if (!$this->recordFinalisedEvent($finalisedEvent)) {
            $this->eventProcessorLogger->critical(
                sprintf(
                    'Failed to record finalised event for %s',
                    (string)$event
                )
            );

            return false;
        }

        return $this->publishClient->dispatch(new PublishableCoverageFailedJobMessage($event));
    }

    #[Override]
    public static function getEvent(): string
    {
        return Event::COVERAGE_FAILED->value;
    }
}
