<?php

namespace App\Handler;

use App\Model\Upload;
use App\Service\CoverageAnalyserService;
use App\Service\CoveragePublisherService;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;
use JsonException;
use Psr\Log\LoggerInterface;

class AnalyseHandler extends SqsHandler
{
    public function __construct(
        private readonly LoggerInterface $handlerLogger,
        private readonly CoverageAnalyserService $coverageAnalyserService,
        private readonly CoveragePublisherService $coveragePublisherService
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

                $upload = new Upload($body);

                $this->handlerLogger->info(sprintf('Starting analysis on %s.', (string)$upload));

                $coverageData = $this->coverageAnalyserService->analyse($upload);

                $successful = $this->coveragePublisherService->publish($upload, $coverageData);

                if (!$successful) {
                    $this->handlerLogger->critical(
                        sprintf(
                            'Attempt to publish coverage for %s was unsuccessful.',
                            (string)$upload
                        )
                    );
                }
            } catch (JsonException) {
                $this->handlerLogger->error(
                    'Error while decoding event.',
                    [
                        'body' => $record->getBody()
                    ]
                );
            }
        }
    }
}
