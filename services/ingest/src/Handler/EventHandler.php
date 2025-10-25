<?php

declare(strict_types=1);

namespace App\Handler;

use App\Exception\DeletionException;
use App\Exception\ParseException;
use App\Exception\PersistException;
use App\Exception\RetrievalException;
use App\Model\Coverage;
use App\Service\CoverageFileParserServiceInterface;
use App\Service\CoverageFilePersistServiceInterface;
use App\Service\CoverageFileRetrievalServiceInterface;
use AsyncAws\S3\Result\GetObjectOutput;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\S3\S3Event;
use Bref\Event\S3\S3Handler;
use Bref\Event\S3\S3Record;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Client\EventBusClient;
use Packages\Event\Client\EventBusClientInterface;
use Packages\Event\Model\IngestFailure;
use Packages\Event\Model\IngestStarted;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\Upload;
use Packages\Telemetry\Enum\Unit;
use Packages\Telemetry\Service\MetricServiceInterface;
use Packages\Telemetry\Service\TraceContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class EventHandler extends S3Handler
{
    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly CoverageFileRetrievalServiceInterface $coverageFileRetrievalService,
        private readonly CoverageFileParserServiceInterface $coverageFileParserService,
        private readonly CoverageFilePersistServiceInterface $coverageFilePersistService,
        #[Autowire(service: EventBusClient::class)]
        private readonly EventBusClientInterface $eventBusClient,
        private readonly LoggerInterface $handlerLogger,
        private readonly MetricServiceInterface $metricService
    ) {
    }

    /**
     * @throws DeletionException
     * @throws InvalidLambdaEvent
     */
    #[Override]
    public function handleS3(S3Event $event, Context $context): void
    {
        TraceContext::setTraceHeaderFromContext($context);

        foreach ($event->getRecords() as $s3Record) {
            try {
                $source = $this->retrieveFile($s3Record);

                $metadata = $this->retrieveFileMetadata($source);

                /** @var Upload $upload */
                $upload = $this->serializer->denormalize(
                    $metadata,
                    Upload::class
                );
            } catch (RetrievalException $e) {
                $this->handlerLogger->error(
                    'Failed to retrieve coverage file.',
                    [
                        'exception' => $e,
                        'bucket' => $s3Record->getBucket(),
                        'key' => $s3Record->getObject()
                    ]
                );

                return;
            } catch (ExceptionInterface $e) {
                $this->handlerLogger->error(
                    'Failed to deserialize upload from metadata of object.',
                    [
                        'exception' => $e,
                        'bucket' => $s3Record->getBucket(),
                        'key' => $s3Record->getObject()
                    ]
                );

                return;
            }

            try {
                $this->handlerLogger->info(
                    sprintf(
                        'Starting to ingest %s for %s.',
                        $s3Record->getObject()->getKey(),
                        $upload
                    )
                );

                $coverage = $this->parseFile(
                    $upload->getProvider(),
                    $upload->getOwner(),
                    $upload->getRepository(),
                    $upload->getProjectRoot(),
                    $source->getBody()
                        ->getContentAsString()
                );

                $this->persistCoverageIfNecessary($upload, $coverage);

                $this->deleteFile($s3Record);

                $this->handlerLogger->info(
                    sprintf(
                        'Successfully ingested and persisted %s using %s parser.',
                        $upload,
                        $coverage->getSourceFormat()->value
                    )
                );
            } catch (ParseException | PersistException $e) {
                $this->handlerLogger->error(
                    'Failed to successfully ingest coverage.',
                    [
                        'exception' => $e,
                        'upload' => $upload
                    ]
                );

                $this->triggerIngestionFailedEvent($upload);
            } catch (DeletionException $e) {
                $this->handlerLogger->error(
                    'Failed to successfully delete ingested coverage file.',
                    [
                        'exception' => $e,
                        'upload' => $upload
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
     * Retrieve the metadata from the coverage file, and convert it into a format
     * which should be able to be used as an upload event.
     *
     * The metadata from S3 will be all lower case, so we need to support the non-camel case
     * naming (hence the need to do this before deserialzation)
     * @throws ExceptionInterface
     */
    private function retrieveFileMetadata(GetObjectOutput $output): array
    {
        return [
            ...$output->getMetadata(),
            'uploadId' => match (true) {
                ($output->getMetadata()['uploadId'] ?? '') !== '' => $output->getMetadata()['uploadId'],
                ($output->getMetadata()['uploadid'] ?? '') !== '' => $output->getMetadata()['uploadid'],
                default => null
            },
            'projectId' => match (true) {
                ($output->getMetadata()['projectId'] ?? '') !== '' => $output->getMetadata()['projectId'],
                ($output->getMetadata()['projectid'] ?? '') !== '' => $output->getMetadata()['projectid'],
                default => null
            },
            'projectRoot' => match (true) {
                ($output->getMetadata()['projectRoot'] ?? '') !== '' => $output->getMetadata()['projectRoot'],
                ($output->getMetadata()['projectroot'] ?? '') !== '' => $output->getMetadata()['projectroot'],
                default => null
            },
            'pullRequest' => match (true) {
                ($output->getMetadata()['pullRequest'] ?? '') !== '' => $output->getMetadata()['pullRequest'],
                ($output->getMetadata()['pullrequest'] ?? '') !== '' => $output->getMetadata()['pullrequest'],
                default => null
            },
            'baseRef' => match (true) {
                ($output->getMetadata()['baseRef'] ?? '') !== '' => $output->getMetadata()['baseRef'],
                ($output->getMetadata()['baseref'] ?? '') !== '' => $output->getMetadata()['baseref'],
                default => null
            },
            'baseCommit' => match (true) {
                ($output->getMetadata()['baseCommit'] ?? '') !== '' => $output->getMetadata()['baseCommit'],
                ($output->getMetadata()['basecommit'] ?? '') !== '' => $output->getMetadata()['basecommit'],
                default => null
            },
            'tag' => [
                'name' => $output->getMetadata()['tag'],
                'commit' => $output->getMetadata()['commit'],
                // Nothings been uploaded yet so we'd expect this to be 0
                'successfullyUploadedLines' => [0]
            ],
            'parent' => $this->serializer->deserialize(
                $output->getMetadata()['parent'],
                'string[]',
                'json'
            )
        ];
    }

    /**
     * Publish an event to EventBridge to indicate that we have started to ingest
     * a new file. This will allow other services to track the progress of the ingestion
     */
    private function triggerIngestionStartedEvent(Upload $upload): bool
    {
        return $this->eventBusClient->fireEvent(
            new IngestStarted(
                $upload,
                new DateTimeImmutable()
            )
        );
    }

    /**
     * Publish an event to EventBridge to indicate that we have finished ingesting a
     * new file successfully. This will allow other services to track the progress of
     * the ingestion
     */
    public function triggerIngestionSuccessEvent(Upload $upload): bool
    {
        $this->metricService->put(
            metric: 'IngestedFiles',
            value: 1,
            unit: Unit::COUNT,
            dimensions: [
                ['owner']
            ],
            properties: [
                'owner' => $upload->getOwner()
            ]
        );

        return $this->eventBusClient->fireEvent(
            new IngestSuccess(
                $upload,
                new DateTimeImmutable()
            )
        );
    }

    private function triggerIngestionFailedEvent(Upload $upload): void
    {
        $this->eventBusClient->fireEvent(
            new IngestFailure(
                $upload,
                new DateTimeImmutable()
            )
        );
    }

    /**
     * Parse an arbitrary file from S3 using a collection of parsing strategies, until
     * one is able to support the file content.
     *
     * @throws ParseException
     */
    private function parseFile(
        Provider $provider,
        string $owner,
        string $repository,
        string $projectRoot,
        string $content
    ): Coverage {
        return $this->coverageFileParserService->parse(
            $provider,
            $owner,
            $repository,
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
    private function persistCoverageIfNecessary(Upload $upload, Coverage $coverage): void
    {
        if (count($coverage) > 0) {
            // We only want to broadcast the ingestion started event if the know theres coverage to
            // persist, as otherwise we can wind up in contention with very fast start and finish events
            $this->triggerIngestionStartedEvent($upload);

            // Only try persisting the parsed coverage if the report contains coverage
            // content. This will prevent us from publishing events when the report was
            // empty coverage
            $didPersist = $this->coverageFilePersistService->persist($upload, $coverage);

            if (!$didPersist) {
                $this->handlerLogger->error(
                    sprintf(
                        'Persisting coverage failed for %s',
                        (string)$upload
                    )
                );

                $this->triggerIngestionFailedEvent($upload);

                return;
            }

            $this->triggerIngestionSuccessEvent($upload);
        }
    }

    /**
     * Delete the coverage file from the ingestion bucket once it has been successfully
     * ingested.
     *
     * @throws DeletionException
     */
    private function deleteFile(S3Record $coverageFile): void
    {
        $this->coverageFileRetrievalService->deleteFromS3(
            $coverageFile->getBucket(),
            $coverageFile->getObject()
        );
    }
}
