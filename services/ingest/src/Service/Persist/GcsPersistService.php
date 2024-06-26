<?php

namespace App\Service\Persist;

use App\Client\BigQueryClient;
use App\Client\BigQueryClientInterface;
use App\Client\GoogleCloudStorageClient;
use App\Client\GoogleCloudStorageClientInterface;
use App\Enum\EnvironmentVariable;
use App\Exception\PersistException;
use App\Model\Coverage;
use App\Model\File;
use App\Service\BigQueryMetadataBuilderService;
use Exception;
use Google\Cloud\Core\ExponentialBackoff;
use Google\Cloud\Storage\StorageObject;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Event\Model\Upload;
use Packages\Telemetry\Enum\Unit;
use Packages\Telemetry\Service\MetricServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class GcsPersistService implements PersistServiceInterface
{
    public const string OUTPUT_BUCKET = 'coverage-loadable-data-%s';

    private const string OUTPUT_KEY = '%s%s.json';

    public function __construct(
        #[Autowire(service: GoogleCloudStorageClient::class)]
        private readonly GoogleCloudStorageClientInterface $googleCloudStorageClient,
        #[Autowire(service: BigQueryClient::class)]
        private readonly BigQueryClientInterface $bigQueryClient,
        private readonly BigQueryMetadataBuilderService $bigQueryMetadataBuilderService,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly LoggerInterface $gcsPersistServiceLogger,
        private readonly MetricServiceInterface $metricService
    ) {
    }

    #[Override]
    public function persist(Upload $upload, Coverage $coverage): bool
    {
        $totalLines = $this->totalLines($coverage);
        $body = $this->getLineCoverageFileBody($upload, $coverage);

        $bucket = $this->googleCloudStorageClient->bucket(
            sprintf(
                self::OUTPUT_BUCKET,
                $this->environmentService->getEnvironment()->value
            )
        );

        $isCoverageLoaded = $this->triggerLoadJob(
            $upload,
            $bucket->upload(
                $body,
                ['name' => sprintf(self::OUTPUT_KEY, '', $upload->getUploadId())]
            )
        );

        if (!$isCoverageLoaded) {
            $this->gcsPersistServiceLogger->error(
                sprintf(
                    'Coverage was not loaded for %s',
                    (string)$upload
                )
            );

            return false;
        }

        $hasAddedUpload = $this->streamUploadRow($upload, $coverage, $totalLines);

        if (!$hasAddedUpload) {
            $this->gcsPersistServiceLogger->error(
                sprintf(
                    'Unable to add upload for persisted coverage %s',
                    (string)$upload
                )
            );

            return false;
        }

        $this->metricService->put(
            metric: 'IngestedLines',
            value: $totalLines,
            unit: Unit::COUNT,
            dimensions: [
                ['owner']
            ],
            properties: [
                'owner' => $upload->getOwner()
            ]
        );

        $this->gcsPersistServiceLogger->info(
            sprintf(
                'Persisting %s to Google Cloud Storage (and loading to BigQuery) has finished',
                (string)$upload
            )
        );

        return true;
    }

    private function triggerLoadJob(Upload $upload, StorageObject $object): bool
    {
        $this->gcsPersistServiceLogger->info(
            sprintf(
                'Triggering load job for uploaded file for %s.',
                (string)$upload
            )
        );

        $table = $this->bigQueryClient->getEnvironmentDataset()
            ->table(
                $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE)
            );

        $loadJob = $table->loadFromStorage(
            $object,
            [
                'configuration' => [
                    'load' => [
                        'sourceFormat' => 'NEWLINE_DELIMITED_JSON'
                    ]
                ]
            ]
        );

        $job = $this->bigQueryClient->runJob($loadJob);

        $backoff = new ExponentialBackoff(10);
        $backoff->execute(function () use ($job, $upload): void {
            $this->gcsPersistServiceLogger->info(
                sprintf(
                    'Waiting for load job %s to complete for %s',
                    $job->id(),
                    (string)$upload
                )
            );

            $job->reload();
            if (!$job->isComplete()) {
                throw new Exception('Job has not yet completed', 500);
            }
        });

        if (isset($job->info()['status']['errorResult'])) {
            $this->gcsPersistServiceLogger->error(
                sprintf(
                    'Unable to load data from GCS object for %s',
                    (string)$upload
                ),
                [
                    'info' => $job->info()
                ]
            );
            return false;
        }

        return true;
    }

    /**
     * Build a temporary file handle containing a new line delimited JSON file
     * consisting of the line coverage data - one row per line of the file handle.
     *
     * @return resource
     */
    public function getLineCoverageFileBody(Upload $upload, Coverage $coverage)
    {
        $buffer = fopen('php://temp', 'rw+');
        $totalLines = $this->totalLines($coverage);

        if (!$buffer) {
            throw new PersistException('Unable to open buffer for writing GCS stream to.');
        }

        foreach ($coverage->getFiles() as $file) {
            foreach ($file->getLines() as $line) {
                fwrite(
                    $buffer,
                    json_encode(
                        $this->bigQueryMetadataBuilderService->buildLineCoverageRow(
                            $upload,
                            $totalLines,
                            $coverage,
                            $file,
                            $line
                        ),
                        JSON_THROW_ON_ERROR
                    ) . "\n"
                );
            }
        }

        return $buffer;
    }

    private function streamUploadRow(Upload $upload, Coverage $coverage, int $totalLines): bool
    {
        $response = $this->bigQueryClient->getEnvironmentDataset()
            ->table(
                $this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_UPLOAD_TABLE)
            )
            ->insertRow($this->bigQueryMetadataBuilderService->buildUploadRow($upload, $coverage, $totalLines));

        return $response->isSuccessful();
    }

    private function totalLines(Coverage $coverage): int
    {
        return array_reduce(
            $coverage->getFiles(),
            static fn(int $totalLines, File $file): int => $totalLines + count($file),
            0
        );
    }

    #[Override]
    public static function getPriority(): int
    {
        return 0;
    }
}
