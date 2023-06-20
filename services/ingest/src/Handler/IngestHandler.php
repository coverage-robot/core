<?php

namespace App\Handler;

use App\Exception\DeletionException;
use App\Exception\ParseException;
use App\Exception\PersistException;
use App\Exception\RetrievalException;
use App\Service\CoverageFileParserService;
use App\Service\CoverageFilePersistService;
use App\Service\CoverageFileRetrievalService;
use AsyncAws\S3\Result\GetObjectOutput;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\S3\S3Event;
use Bref\Event\S3\S3Handler;
use Bref\Event\S3\S3Record;
use JsonException;
use Packages\Models\Model\Project;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;
use RuntimeException;

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
     * @throws DeletionException
     * @throws InvalidLambdaEvent
     * @throws JsonException
     */
    public function handleS3(S3Event $event, Context $context): void
    {
        foreach ($event->getRecords() as $coverageFile) {
            $source = $this->retrieveFile($coverageFile);
            $upload = Upload::from($source->getMetadata());

            $this->handlerLogger->info(
                sprintf(
                    'Starting to ingest %s for %s.',
                    $coverageFile->getObject()->getKey(),
                    (string)$upload
                )
            );

            $projectRoot = $this->getProjectRoot($source);

            try {
                $coverage = $this->parseFile(
                    $projectRoot,
                    $source->getBody()->getContentAsString()
                );

                $this->persistCoverage($upload, $coverage);

                $this->deleteFile($coverageFile);

                $this->handlerLogger->info(
                    sprintf(
                        'Successfully ingested and persisted %s using %s parser.',
                        (string)$upload,
                        $coverage->getSourceFormat()->value
                    )
                );
            } catch (ParseException | PersistException | DeletionException $e) {
                $this->handlerLogger->error(
                    sprintf('Failed to successfully ingest %s.', (string)$upload),
                    [
                        'exception' => $e
                    ]
                );
            }
        }
    }

    /**
     * Retrieve the coverage file from S3.
     *
     * @throws RetrievalException
     */
    private function retrieveFile(S3Record $coverageFile): GetObjectOutput
    {
        return $this->coverageFileRetrievalService->ingestFromS3(
            $coverageFile->getBucket(),
            $coverageFile->getObject()
        );
    }

    /**
     * Get the project root from the coverage file metadata.
     *
     * The key must be in all lowercase, as the metadata pulled back from S3 will
     * have already lost its case.
     *
     * @throws RetrievalException
     */
    private function getProjectRoot(GetObjectOutput $object): string
    {
        $metadata = $object->getMetadata();

        if (!isset($metadata['projectroot'])) {
            throw RetrievalException::from(
                new RuntimeException('Missing project root from metadata')
            );
        }

        return (string)$metadata['projectroot'];
    }

    /**
     * Parse an arbitrary file from S3 using a collection of parsing strategies, until
     * one is able to support the file content.
     *
     * @throws ParseException
     */
    private function parseFile(string $projectRoot, string $content): Project
    {
        return $this->coverageFileParserService->parse(
            $projectRoot,
            $content
        );
    }

    /**
     * Persist a successfully parsed coverage file into a collection of storage locations.
     *
     * Examples include:
     * - BigQuery
     * - Sqs
     * - S3
     *
     * @throws PersistException
     */
    private function persistCoverage(Upload $upload, Project $coverage): bool
    {
        return $this->coverageFilePersistService->persist($upload, $coverage);
    }

    /**
     * Delete the coverage file from the ingestion bucket once it has been successfully
     * ingested.
     *
     * @throws DeletionException
     */
    private function deleteFile(S3Record $coverageFile): bool
    {
        return $this->coverageFileRetrievalService->deleteFromS3(
            $coverageFile->getBucket(),
            $coverageFile->getObject()
        );
    }
}
