<?php

namespace App\Service\Persist;

use App\Client\BigQueryClient;
use App\Model\File;
use App\Model\Line\AbstractLineCoverage;
use App\Model\Project;
use App\Model\Upload;
use Psr\Log\LoggerInterface;

class BigQueryPersistService implements PersistServiceInterface
{
    public function __construct(
        private readonly BigQueryClient $bigQueryClient,
        private readonly LoggerInterface $persistServiceLogger
    ) {
    }

    public function persist(Upload $upload): bool
    {
        $table = $this->bigQueryClient->getLineAnalyticsDataset()
            ->table('lines');

        $rows = $this->buildRows($upload->getProject(), $upload->getUploadId());

        $insertResponse = $table->insertRows($rows);

        if (!$insertResponse->isSuccessful()) {
            $this->persistServiceLogger->critical(
                sprintf(
                    '%s row error(s) while attempting to persist coverage file (%s) into BigQuery.',
                    $upload,
                    count($insertResponse->failedRows())
                ),
                [
                    'failedRows' => $insertResponse->failedRows()
                ]
            );

            return false;
        }

        $this->persistServiceLogger->info(
            sprintf(
                'Persisting %s (%s rows) into BigQuery was successful',
                $upload,
                count($rows)
            )
        );

        return true;
    }

    private function buildRows(Project $project, string $uniqueId): array
    {
        return array_reduce(
            $project->getFiles(),
            function (array $carry, File $file) use ($project, $uniqueId): array {
                return [
                    ...$carry,
                    ...array_map(
                        fn(AbstractLineCoverage $line): array => [
                            'data' => $this->buildRow($uniqueId, $project, $file, $line)
                        ],
                        $file->getAllLineCoverage()
                    )
                ];
            },
            []
        );
    }

    private function buildRow(string $uniqueId, Project $project, File $file, AbstractLineCoverage $line): array
    {
        return [
            'id' => $uniqueId,
            'sourceFormat' => $project->getSourceFormat()->name,
            'fileName' => $file->getFileName(),
            'generatedAt' => $project->getGeneratedAt() ?
                $project->getGeneratedAt()?->format('Y-m-d H:i:s') :
                null,
            'type' => $line->getType()->name,
            'lineNumber' => $line->getLineNumber(),
            'metadata' => array_map(
                static fn($key, $value) => [
                    'key' => (string)$key,
                    'value' => (string)$value
                ],
                array_keys($line->jsonSerialize()),
                array_values($line->jsonSerialize())
            )
        ];
    }

    public static function getPriority(): int
    {
        return 0;
    }
}
