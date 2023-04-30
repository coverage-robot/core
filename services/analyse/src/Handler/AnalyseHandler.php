<?php

namespace App\Handler;

use App\Model\Event\IngestCompleteEvent;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;
use Psr\Log\LoggerInterface;

class AnalyseHandler extends SqsHandler
{
    public function __construct(
        private readonly LoggerInterface $handlerLogger
    )
    {
    }

    public function handleSqs(SqsEvent $event, Context $context): void
    {
        foreach ($event->getRecords() as $record) {
            $event = new IngestCompleteEvent($record->getBody());

            $this->handlerLogger->info(sprintf("Starting analysis on %s coverage upload.", $event->getUniqueId()));
        }
    }
}
