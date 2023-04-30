<?php

namespace App\Handler;

use App\Exception\ParseException;
use App\Service\CoverageFileParserService;
use App\Service\CoverageFilePersistService;
use App\Service\CoverageFileRetrievalService;
use App\Service\UniqueIdGeneratorService;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\S3\S3Event;
use Bref\Event\S3\S3Handler;

class IngestHandler extends S3Handler
{
    public function __construct(
        private readonly CoverageFileRetrievalService $coverageFileRetrievalService,
        private readonly CoverageFileParserService $coverageFileParserService,
        private readonly CoverageFilePersistService $coverageFilePersistService,
        private readonly UniqueIdGeneratorService $uniqueIdGenerator
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

            $uniqueCoverageId = $this->uniqueIdGenerator->generate();

            try {
                $coverage = $this->coverageFileParserService->parse($source);

                $this->coverageFilePersistService->persist($coverage, $uniqueCoverageId);
            } catch (ParseException) {
                // Something went wrong during parsing. In the future, we should log this.
                continue;
            }
        }
    }
}
