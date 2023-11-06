<?php

namespace App\Event;

use App\Client\DynamoDbClient;
use App\Enum\OrchestratedEventState;
use App\Model\Job;
use App\Service\EventStoreService;
use Packages\Event\Enum\Event;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\JobStateChange;
use Packages\Models\Enum\JobState;
use Psr\Log\LoggerInterface;

class JobStateChangeEventProcessor extends AbstractOrchestratorEventRecorderProcessor
{
    public function __construct(
        EventStoreService $eventStoreService,
        DynamoDbClient $dynamoDbClient,
        private readonly LoggerInterface $jobEventProcessorLogger
    ) {
        parent::__construct(
            $eventStoreService,
            $dynamoDbClient,
            $jobEventProcessorLogger
        );
    }

    public function process(EventInterface $event): bool
    {
        if (!$event instanceof JobStateChange) {
            $this->jobEventProcessorLogger->critical(
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
            $event->getExternalId()
        );

        return $this->recordStateChangeInStore($newState);
    }

    public static function getEvent(): string
    {
        return Event::JOB_STATE_CHANGE->value;
    }
}
