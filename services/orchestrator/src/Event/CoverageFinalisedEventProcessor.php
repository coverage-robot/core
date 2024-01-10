<?php

namespace App\Event;

use App\Enum\OrchestratedEventState;
use App\Event\Backoff\BackoffStrategyInterface;
use App\Event\Backoff\EventStoreRecorderBackoffStrategy;
use App\Model\Finalised;
use App\Service\CachingEventStoreService;
use App\Service\EventStoreServiceInterface;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Model\CoverageFinalised;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CoverageFinalisedEventProcessor extends AbstractOrchestratorEventRecorderProcessor
{
    use OverallCommitStateAwareTrait;

    public function __construct(
        #[Autowire(service: CachingEventStoreService::class)]
        private readonly EventStoreServiceInterface $eventStoreService,
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

    /**
     * Listen for events where coverage has been finalised for a commit, and close the loop on the finalised event
     * by recording the success of the coverage event.
     */
    #[Override]
    public function process(EventInterface $event): bool
    {
        if (!$event instanceof CoverageFinalised) {
            $this->eventProcessorLogger->critical(
                'Event is not intended to be processed by this processor',
                [
                    'event' => $event
                ]
            );
            return false;
        }

        $currentState = new Finalised(
            $event->getProvider(),
            $event->getOwner(),
            $event->getRepository(),
            $event->getRef(),
            $event->getCommit(),
            OrchestratedEventState::SUCCESS,
            null,
            $event->getEventTime()
        );

        return $this->recordStateChangeInStore($currentState);
    }

    #[\Override]
    public static function getEvent(): string
    {
        return Event::COVERAGE_FINALISED->value;
    }
}
