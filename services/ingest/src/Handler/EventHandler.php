<?php

namespace App\Handler;

use App\Exception\DeletionException;
use App\Exception\ParseException;
use App\Exception\PersistException;
use App\Exception\RetrievalException;
use App\Model\Coverage;
use App\Service\CoverageFileParserService;
use App\Service\CoverageFilePersistService;
use App\Service\CoverageFileRetrievalService;
use AsyncAws\S3\Result\GetObjectOutput;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\S3\S3Event;
use Bref\Event\S3\S3Handler;
use Bref\Event\S3\S3Record;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\EventSource;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Client\EventBusClient;
use Packages\Event\Model\IngestFailure;
use Packages\Event\Model\IngestStarted;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\Upload;
use Packages\Telemetry\Enum\Unit;
use Packages\Telemetry\Service\MetricService;
use Packages\Telemetry\Service\TraceContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class EventHandler extends S3Handler
{
    public function __construct(
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        private readonly CoverageFileRetrievalService $coverageFileRetrievalService,
        private readonly CoverageFileParserService $coverageFileParserService,
        private readonly CoverageFilePersistService $coverageFilePersistService,
        private readonly EventBusClient $eventBusClient,
        private readonly LoggerInterface $handlerLogger,
        private readonly MetricService $metricService
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

        foreach ($event->getRecords() as $coverageFile) {
            try {
                $source = $this->retrieveFile($coverageFile);

                $metadata = $this->retrieveFileMetadata($source);

                /** @var Upload $upload */
                $upload = $this->serializer->denormalize(
                    $metadata,
                    Upload::class
                );

                $this->handlerLogger->info(
                    sprintf(
                        'Starting to ingest %s for %s.',
                        $coverageFile->getObject()->getKey(),
                        (string)$upload
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

                if (count($coverage) > 0) {
                    // We only want to broadcast the ingestion started event if the know theres coverage to
                    // persist, as otherwise we can wind up in contention with very fast start and finish events
                    $this->triggerIngestionStartedEvent($upload);

                    // Only try persisting the parsed coverage if the report contains coverage
                    // content. This will prevent us from publishing events when the report was
                    // empty coverage
                    $this->persistCoverage($upload, $coverage);
                }

                $this->triggerIngestionSuccessEvent($upload);

                $this->deleteFile($coverageFile);

                $this->handlerLogger->info(
                    sprintf(
                        'Successfully ingested and persisted %s using %s parser.',
                        (string)$upload,
                        $coverage->getSourceFormat()->value
                    )
                );
            } catch (RetrievalException $e) {
                $this->handlerLogger->error(
                    'Failed to retrieve coverage file.',
                    [
                        'exception' => $e,
                        'bucket' => $coverageFile->getBucket(),
                        'key' => $coverageFile->getObject()
                    ]
                );
            } catch (ParseException | PersistException $e) {
                $this->handlerLogger->error(
                    'Failed to successfully ingest coverage.',
                    [
                        'exception' => $e,
                        'upload' => $upload ?? null
                    ]
                );

                $this->eventBusClient->fireEvent(
                    EventSource::INGEST,
                    new IngestFailure(
                        $upload,
                        new DateTimeImmutable()
                    )
                );
            } catch (DeletionException $e) {
                $this->handlerLogger->error(
                    'Failed to successfully delete ingested coverage file.',
                    [
                        'exception' => $e,
                        'upload' => $upload ?? null
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
                'commit' => $output->getMetadata()['commit']
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
            EventSource::INGEST,
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
            EventSource::INGEST,
            new IngestSuccess(
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
    private function persistCoverage(Upload $upload, Coverage $coverage): bool
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
