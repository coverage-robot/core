<?php

namespace App\Event;

use App\Client\DynamoDbClient;
use App\Enum\OrchestratedEventState;
use App\Model\Ingestion;
use App\Service\EventStoreService;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\IngestFailure;
use Packages\Event\Model\IngestSuccess;
use Psr\Log\LoggerInterface;

abstract class AbstractIngestEventProcessor extends AbstractOrchestratorEventRecorderProcessor
{
    public function __construct(
        EventStoreService $eventStoreService,
        DynamoDbClient $dynamoDbClient,
        private readonly LoggerInterface $ingestEventProcessorLogger
    ) {
        parent::__construct(
            $eventStoreService,
            $dynamoDbClient,
            $ingestEventProcessorLogger
        );
    }

    public function process(EventInterface $event): bool
    {
        if (
            !$event instanceof IngestSuccess &&
            !$event instanceof IngestFailure
        ) {
            $this->ingestEventProcessorLogger->critical(
                'Event is not intended to be processed by this processor',
                [
                    'event' => $event::class
                ]
            );
            return false;
        }

        $newState = new Ingestion(
            $event->getProvider(),
            $event->getOwner(),
            $event->getRepository(),
            $event->getCommit(),
            match (true) {
                $event instanceof IngestSuccess => OrchestratedEventState::SUCCESS,
                $event instanceof IngestFailure => OrchestratedEventState::FAILURE
            }
        );

        return $this->recordStateChangeInStore($newState);
    }
}
