<?php

namespace App\Handler;

use App\Exception\ParseException;
use App\Service\CoverageFileParserService;
use App\Service\CoverageFilePersistService;
use App\Service\CoverageFileRetrievalService;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\S3\S3Event;
use Bref\Event\S3\S3Handler;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;

class IngestHandler extends S3Handler
{
    public function __construct(
        private readonly CoverageFileRetrievalService $coverageFileRetrievalService,
        private readonly CoverageFileParserService $coverageFileParserService,
        private readonly CoverageFilePersistService $coverageFilePersistService,
        private readonly LoggerInterface $handlerLogger
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

            $upload = Upload::from($source->getMetadata());

            $this->handlerLogger->info(
                sprintf(
                    'Starting to ingest %s for %s.',
                    $coverageFile->getObject()->getKey(),
                    (string)$upload
                )
            );

            try {
                $coverage = $this->coverageFileParserService->parse($source->getBody()->getContentAsString());

                $this->handlerLogger->info(
                    sprintf(
                        'Successfully parsed %s using %s parser.',
                        (string)$upload,
                        $coverage->getSourceFormat()->value
                    )
                );

                $persisted = $this->coverageFilePersistService->persist($upload, $coverage);

                if (!$persisted) {
                    $this->handlerLogger->error(
                        sprintf(
                            'Failed to fully persist %s into storage.',
                            (string)$upload
                        )
                    );

                    return;
                }

                // Finally, delete the coverage file now that we've successfully ingested it
                $this->coverageFileRetrievalService->deleteIngestedFile(
                    $coverageFile->getBucket(),
                    $coverageFile->getObject()
                );
            } catch (ParseException $e) {
                $this->handlerLogger->error(
                    sprintf('Exception received while attempting to parse %s.', (string)$upload),
                    [
                        'exception' => $e
                    ]
                );
            }
        }
    }
}
