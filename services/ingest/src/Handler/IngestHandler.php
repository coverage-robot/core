<?php

namespace App\Handler;

use App\Service\CoverageFileRetrievalService;
use App\Service\CoverageFileParserService;
use Bref\Context\Context;
use Bref\Event\S3\S3Event;
use Bref\Event\S3\S3Handler;

class IngestHandler extends S3Handler
{
    public function __construct(
        private readonly CoverageFileRetrievalService $coverageFileRetrievalService,
        private readonly CoverageFileParserService $coverageFileParserService
    ) {
    }

    public function handleS3(S3Event $event, Context $context): void
    {
        foreach ($event->getRecords() as $coverageFile) {
            $source = $this->coverageFileRetrievalService->ingestFromS3(
                $coverageFile->getBucket(),
                $coverageFile->getObject()
            );

            print_r($this->coverageFileParserService->parse($source));
        }
    }
}
