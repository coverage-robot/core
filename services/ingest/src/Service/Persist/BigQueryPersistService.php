<?php

namespace App\Service\Persist;

use App\Client\BigQueryClient;
use App\Enum\EnvironmentVariable;
use App\Service\BigQueryMetadataBuilderService;
use App\Service\EnvironmentService;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;

class BigQueryPersistService implements PersistServiceInterface
{
    public function __construct(
        private readonly BigQueryClient $bigQueryClient,
        private readonly BigQueryMetadataBuilderService $bigQueryMetadataBuilderService,
        private readonly EnvironmentService $environmentService,
        private readonly LoggerInterface $bigQueryPersistServiceLogger,
        private readonly int $chunkSize = 10000
    ) {
    }

    public function persist(Upload $upload, Coverage $coverage): bool
    {
        $table = $this->bigQueryClient->getEnvironmentDataset()
            ->table($this->environmentService->getVariable(EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE));

        $partialFailure = false;

        foreach ($this->getChunkedLines($upload, $coverage, $this->chunkSize) as $chunkNumber => $rows) {
            $insertResponse = $table->insertRows($rows);
            $failedRows = $insertResponse->failedRows();

            $this->bigQueryPersistServiceLogger->info(
                sprintf(
                    'Persistence of %s rows into BigQuery complete (chunk: %s for %s). Failed to insert %s rows.',
                    count($rows),
                    $chunkNumber,
                    (string)$upload,
                    count($failedRows)
                ),
                [
                    'failedRows' => $failedRows
                ]
            );

            $partialFailure = $partialFailure || !$insertResponse->isSuccessful();
        }

        return !$partialFailure;
    }

    /**
     * Build a set of chunked rows to be inserted into BigQuery.
     *
     * This method has to be very memory efficient as it will be an accumulator for _every_ line in _every_ file of
     * the coverage file - potentially resulting in hundreds of thousands of rows at a time.
     *
     * @return iterable<int, array>
     */
    private function getChunkedLines(Upload $upload, Coverage $coverage, int $chunkSize): iterable
    {
        $chunk = [];

        $remainingFiles = count($coverage);

        foreach ($coverage->getFiles() as $file) {
            $remainingLines = count($file);

            $remainingFiles--;

            foreach ($file->getAllLines() as $line) {
                $chunk[] = [
                    'data' => $this->bigQueryMetadataBuilderService->buildRow($upload, $coverage, $file, $line)
                ];

                $remainingLines--;

                if (
                    $remainingFiles == 0 &&
                    $remainingLines == 0
                ) {
                    yield $chunk;
                    return;
                }

                if (count($chunk) === $chunkSize) {
                    yield $chunk;
                    $chunk = [];
                }
            }
        }
    }

    public static function getPriority(): int
    {
        return 0;
    }
}
