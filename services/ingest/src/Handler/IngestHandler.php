<?php

namespace App\Handler;

use App\Exception\ParseException;
use App\Model\Upload;
use App\Service\CoverageFileParserService;
use App\Service\CoverageFilePersistService;
use App\Service\CoverageFileRetrievalService;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\S3\S3Event;
use Bref\Event\S3\S3Handler;
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

            $uploadId = $source->getMetadata()['uploadid'];
            $provider = $source->getMetadata()['provider'];
            $owner = $source->getMetadata()['owner'];
            $repository = $source->getMetadata()['repository'];
            $commit = $source->getMetadata()['commit'];
            $ref = $source->getMetadata()['ref'];
            $pullRequest = $source->getMetadata()['pullrequest'] ?? null;
            $tag = $source->getMetadata()['tag'];

            /** @var string[] $parent */
            $parent = json_decode($source->getMetadata()['parent'], true, JSON_THROW_ON_ERROR);

            $this->handlerLogger->info(
                sprintf(
                    'Starting to ingest %s with id of %s.',
                    $coverageFile->getObject()->getKey(),
                    $uploadId
                )
            );

            try {
                $coverage = $this->coverageFileParserService->parse($source->getBody()->getContentAsString());

                $upload = new Upload(
                    $coverage,
                    $uploadId,
                    $provider,
                    $owner,
                    $repository,
                    $commit,
                    $parent,
                    $ref,
                    $pullRequest,
                    $tag,
                    $coverageFile->getEventTime()
                );

                $this->handlerLogger->info(
                    sprintf(
                        'Successfully parsed %s using %s parser.',
                        $uploadId,
                        $coverage->getSourceFormat()->value
                    )
                );

                $persisted = $this->coverageFilePersistService->persist($upload);

                if (!$persisted) {
                    $this->handlerLogger->error(
                        sprintf(
                            'Failed to fully persist %s into storage.',
                            $uploadId
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
                    sprintf('Exception received while attempting to parse %s.', $uploadId),
                    [
                        'exception' => $e
                    ]
                );
            }
        }
    }
}
