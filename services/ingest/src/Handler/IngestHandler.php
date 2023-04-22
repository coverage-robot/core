<?php

namespace App\Handler;

use App\Service\CoverageFilePersistService;
use App\Service\CoverageFileRetrievalService;
use App\Service\CoverageFileParserService;
use App\Service\EnvironmentService;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\S3\S3Event;
use Bref\Event\S3\S3Handler;

class IngestHandler extends S3Handler
{
    const OUTPUT_BUCKET = "coverage-output-%s";

    public function __construct(
        private readonly EnvironmentService $environmentService,
        private readonly CoverageFileRetrievalService $coverageFileRetrievalService,
        private readonly CoverageFileParserService $coverageFileParserService,
        private readonly CoverageFilePersistService $coverageFilePersistService
    ) {
    }

    /**
     * @throws InvalidLambdaEvent
     */
    public function handleS3(S3Event $event, Context $context): void
    {
        foreach ($event->getRecords() as $coverageFile) {
            $source = $this->coverageFileRetrievalService->ingestFromS3(
                $coverageFile->getBucket(),
                $coverageFile->getObject()
            );

            $prefix = dirname($coverageFile->getObject()->getKey())."/";

            $outputKey = sprintf(
                "%s%s.json",
                $prefix !== "./" ? $prefix : "",
                pathinfo($coverageFile->getObject()->getKey(), PATHINFO_FILENAME)
            );

            $this->coverageFilePersistService->persistToS3(
                sprintf(self::OUTPUT_BUCKET, $this->environmentService->getEnvironment()->value),
                $outputKey,
                $this->coverageFileParserService->parse($source)
            );
        }
    }
}
