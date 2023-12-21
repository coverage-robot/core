<?php

namespace App\Service\Event;

use App\Client\SqsMessageClient;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Model\UploadsStarted;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use Psr\Log\LoggerInterface;
use RuntimeException;

class UploadsStartedEventProcessor implements EventProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface $eventProcessorLogger,
        private readonly SqsMessageClient $sqsMessageClient
    ) {
    }

    public function process(EventInterface $event): bool
    {
        if (!$event instanceof UploadsStarted) {
            throw new RuntimeException(
                sprintf(
                    'Event is not an instance of %s',
                    UploadsStarted::class
                )
            );
        }

        $successful = $this->queueStartCheckRun($event);

        if (!$successful) {
            $this->eventProcessorLogger->critical(
                sprintf(
                    'Attempt to publish coverage for %s was unsuccessful.',
                    (string)$event
                )
            );
        }

        return $successful;
    }

    /**
     * Write all of the publishable coverage data messages onto the queue for the _starting_ check
     * run state, ready to be picked up and published to the version control provider.
     *
     * Right now, this is:
     * 2. An in progress check run
     */
    private function queueStartCheckRun(UploadsStarted $uploadsStarted): bool
    {
        return $this->sqsMessageClient->queuePublishableMessage(
            new PublishableCheckRunMessage(
                event: $uploadsStarted,
                status: PublishableCheckRunStatus::IN_PROGRESS,
                coveragePercentage: 0,
                coverageChange: 0,
                validUntil: $uploadsStarted->getEventTime()
            )
        );
    }

    public static function getEvent(): string
    {
        return Event::UPLOADS_STARTED->value;
    }
}
