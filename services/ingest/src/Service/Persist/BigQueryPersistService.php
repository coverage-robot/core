<?php

namespace App\Service\Persist;

use App\Client\BigQueryClient;
use Packages\Models\Model\File;
use Packages\Models\Model\Line\AbstractLineCoverage;
use Packages\Models\Model\Project;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;

class BigQueryPersistService implements PersistServiceInterface
{
    public function __construct(
        private readonly BigQueryClient $bigQueryClient,
        private readonly LoggerInterface $bigQueryPersistServiceLogger
    ) {
    }

    public function persist(Upload $upload, Project $project): bool
    {
        $table = $this->bigQueryClient->getEnvironmentDataset()
            ->table($_ENV['BIGQUERY_LINE_COVERAGE_TABLE']);

        $rows = $this->buildRows($upload, $project);

        $insertResponse = $table->insertRows($rows);

        if (!$insertResponse->isSuccessful()) {
            $this->bigQueryPersistServiceLogger->critical(
                sprintf(
                    '%s row error(s) while attempting to persist coverage file (%s) into BigQuery.',
                    (string)$upload,
                    count($insertResponse->failedRows())
                ),
                [
                    'failedRows' => $insertResponse->failedRows()
                ]
            );

            return false;
        }

        $this->bigQueryPersistServiceLogger->info(
            sprintf(
                'Persisting %s (%s rows) into BigQuery was successful',
                (string)$upload,
                count($rows)
            )
        );

        return true;
    }

    private function buildRows(Upload $upload, Project $project): array
    {
        return array_reduce(
            $project->getFiles(),
            function (array $carry, File $file) use ($upload, $project): array {
                return [
                    ...$carry,
                    ...array_map(
                        fn(AbstractLineCoverage $line): array => [
                            'data' => $this->buildRow($upload, $project, $file, $line)
                        ],
                        $file->getAllLineCoverage()
                    )
                ];
            },
            []
        );
    }

    private function buildRow(Upload $upload, Project $project, File $file, AbstractLineCoverage $line): array
    {
        return [
            'uploadId' => $upload->getUploadId(),
            'ingestTime' => $upload->getIngestTime()->format('Y-m-d H:i:s'),
            'provider' => $upload->getProvider()->value,
            'owner' => $upload->getOwner(),
            'repository' => $upload->getRepository(),
            'commit' => $upload->getCommit(),
            'parent' => $upload->getParent(),
            'ref' => $upload->getRef(),
            'tag' => $upload->getTag(),
            'sourceFormat' => $project->getSourceFormat(),
            'fileName' => $file->getFileName(),
            'generatedAt' => $project->getGeneratedAt() ?
                $project->getGeneratedAt()?->format('Y-m-d H:i:s') :
                null,
            'type' => $line->getType(),
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
