<?php

namespace App\Service\Persist;

use App\Client\BigQueryClient;
use App\Client\GoogleCloudStorageClient;
use App\Enum\EnvironmentVariable;
use App\Exception\PersistException;
use App\Service\BigQueryMetadataBuilderService;
use App\Service\EnvironmentService;
use Exception;
use Google\Cloud\Core\ExponentialBackoff;
use Google\Cloud\Storage\StorageObject;
use JsonException;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\File;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class GcsPersistService implements PersistServiceInterface
{
    public const OUTPUT_BUCKET = 'coverage-loadable-data-%s';

    private const OUTPUT_KEY = '%s%s.json';

    /**
     * @param SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer
     */
    public function __construct(
        private readonly GoogleCloudStorageClient $googleCloudStorageClient,
        private readonly BigQueryClient $bigQueryClient,
        private readonly BigQueryMetadataBuilderService $bigQueryMetadataBuilderService,
        private readonly EnvironmentService $environmentService,
        private readonly LoggerInterface $gcsPersistServiceLogger
    ) {
    }

    /**
     * @param Upload $upload
     * @param Coverage $coverage
     * @return bool
     * @throws JsonException
     */
    public function persist(Upload $upload, Coverage $coverage): bool
    {
        $body = $this->getBody($upload, $coverage);

        $bucket = $this->googleCloudStorageClient->bucket(
            sprintf(
                self::OUTPUT_BUCKET,
                $this->environmentService->getEnvironment()->value
            )
        );

        $isLoaded = $this->triggerLoadJob(
            $upload,
            $bucket->upload(
                $body,
                ['name' => sprintf(self::OUTPUT_KEY, '', $upload->getUploadId())]
            )
        );

        fclose($body);

        $this->gcsPersistServiceLogger->info(
            sprintf(
                'Persisting %s to Google Cloud Storage (and loading to BigQuery) has finished',
                (string)$upload
            ),
            [
                'success' => $isLoaded,
            ]
        );

        return $isLoaded;
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
        $backoff->execute(function () use ($job, $upload) {
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
     * @return resource
     */
    public function getBody(Upload $upload, Coverage $coverage)
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
                        $this->bigQueryMetadataBuilderService->buildRow(
                            $upload,
                            $totalLines,
                            $coverage,
                            $file,
                            $line
                        )
                    ) . "\n"
                );
            }
        }

        return $buffer;
    }

    public function totalLines(Coverage $coverage): int
    {
        return array_reduce(
            $coverage->getFiles(),
            static fn(int $totalLines, File $file) => $totalLines + count($file),
            0
        );
    }

    public static function getPriority(): int
    {
        return 0;
    }
}
