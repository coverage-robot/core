<?php

namespace App\Service\Persist;

use App\Client\BigQueryClient;
use App\Enum\EnvironmentVariable;
use App\Service\BigQueryMetadataBuilderService;
use App\Service\EnvironmentService;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\File;
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

        $totalLines = $this->totalLines($coverage);

        foreach ($this->getChunkedLines($upload, $totalLines, $coverage, $this->chunkSize) as $chunkNumber => $rows) {
            $insertResponse = $table->insertRows($rows);
            $failedRows = $insertResponse->failedRows();

            $this->bigQueryPersistServiceLogger->info(
                sprintf(
                    'Persistence of %s rows into BigQuery complete (chunk: %s for %s).',
                    count($rows),
                    $chunkNumber,
                    (string)$upload,
                )
            );

            if (count($failedRows) > 0) {
                $this->bigQueryPersistServiceLogger->critical(
                    sprintf(
                        '%s rows failed to insert in chunk %s for %s.',
                        count($failedRows),
                        $chunkNumber,
                        (string)$upload
                    ),
                    [
                        'failedRows' => $failedRows
                    ]
                );
            }

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
    private function getChunkedLines(Upload $upload, int $totalLines, Coverage $coverage, int $chunkSize): iterable
    {
        $remainingLines = $totalLines;

        foreach ($coverage->getFiles() as $file) {
            foreach ($file->getAllLines() as $line) {
                $chunk = [
                    ...($chunk ?? []),
                    [
                        'uploadId' => md5(
                            implode('-', [
                                $upload->getUploadId(),
                                $file->getFileName(),
                                $line->getLineNumber()
                            ])
                        ),
                        'data' => $this->bigQueryMetadataBuilderService->buildRow(
                            $upload,
                            $totalLines,
                            $coverage,
                            $file,
                            $line
                        )
                    ]
                ];

                $remainingLines--;

                if (count($chunk) === $chunkSize) {
                    yield $chunk;
                    $chunk = [];
                }
            }

            if (
                !empty($chunk) &&
                $remainingLines == 0
            ) {
                yield $chunk;
                return;
            }
        }
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
