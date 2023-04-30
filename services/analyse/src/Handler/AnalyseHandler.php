<?php

namespace App\Handler;

use App\Model\Event\IngestCompleteEvent;
use App\Service\CoverageAnalyserService;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;
use JsonException;
use Psr\Log\LoggerInterface;

class AnalyseHandler extends SqsHandler
{
    public function __construct(
        private readonly LoggerInterface $handlerLogger,
        private readonly CoverageAnalyserService $coverageAnalyserService
    ) {
    }

    public function handleSqs(SqsEvent $event, Context $context): void
    {
        foreach ($event->getRecords() as $record) {
            try {
                $body = json_decode($record->getBody(), true, JSON_THROW_ON_ERROR);

                if (!is_array($body)) {
                    $this->handlerLogger->info('Message body was not valid.');
                    continue;
                }

                $event = new IngestCompleteEvent($body);

                $this->handlerLogger->info(
                    sprintf('Starting analysis on %s coverage upload.', $event->getUniqueId())
                );

                $this->coverageAnalyserService->analyse($event->getUniqueId());
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
