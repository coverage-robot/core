<?php

namespace App\Handler;

use App\Exception\ParseException;
use App\Service\CoverageFileParserService;
use App\Service\CoverageFilePersistService;
use App\Service\CoverageFileRetrievalService;
use App\Service\EnvironmentService;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\S3\S3Event;
use Bref\Event\S3\S3Handler;
use Ramsey\Uuid\Uuid;

class IngestHandler extends S3Handler
{
    private const OUTPUT_BUCKET = 'coverage-output-%s';

    private const OUTPUT_KEY = '%s%s.json';

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

            $prefix = dirname($coverageFile->getObject()->getKey()) . '/';

            $uniqueCoverageId = Uuid::uuid4();

            $outputKey = sprintf(
                self::OUTPUT_KEY,
                $prefix !== './' ? $prefix : '',
                $uniqueCoverageId->toString()
            );

            try {
                $coverage = $this->coverageFileParserService->parse($source);

                $this->coverageFilePersistService->persistToS3(
                    sprintf(self::OUTPUT_BUCKET, $this->environmentService->getEnvironment()->value),
                    $outputKey,
                    $coverage
                );

                $this->coverageFilePersistService->persistToBigQuery($coverage, $uniqueCoverageId->toString());
            } catch (ParseException) {
                // Something went wrong during parsing. In the future, we should log this.
                continue;
            }
        }
    }
}
