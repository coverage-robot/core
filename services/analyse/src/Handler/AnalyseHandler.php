<?php

namespace App\Handler;

use App\Model\Event\IngestCompleteEvent;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;
use JsonException;
use Psr\Log\LoggerInterface;

class AnalyseHandler extends SqsHandler
{
    public function __construct(
        private readonly LoggerInterface $handlerLogger
    ) {
    }

    public function handleSqs(SqsEvent $event, Context $context): void
    {
        foreach ($event->getRecords() as $record) {
            try {
                $event = new IngestCompleteEvent(
                    json_decode($record->getBody(), JSON_THROW_ON_ERROR)
                );

                $this->handlerLogger->info(
                    sprintf('Starting analysis on %s coverage upload.', $event->getUniqueId())
                );
            } catch (JsonException) {
                $this->handlerLogger->error(
                    'Error while decoding ingest completion event.',
                    [
                        'body' => $record->getBody()
                    ]
                );
            }
        }
    }
}
