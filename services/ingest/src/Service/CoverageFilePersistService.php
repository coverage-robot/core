<?php

namespace App\Service;

use App\Client\BigQueryClient;
use App\Exception\PersistException;
use App\Model\File;
use App\Model\Line\AbstractLineCoverage;
use App\Model\Line\BranchCoverage;
use App\Model\Project;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

class CoverageFilePersistService
{
    public function __construct(
        private readonly S3Client $s3Client,
        private readonly BigQueryClient $bigQueryClient
    ) {
    }

    /**
     * @throws JsonException
     */
    public function persistToS3(string $bucket, string $key, Project $projectCoverage): bool
    {
        try {
            $response = $this->s3Client->putObject(
                new PutObjectRequest(
                    [
                        'Bucket' => $bucket,
                        'Key' => $key,
                        'ContentType' => 'application/json',
                        'Metadata' => [
                            'sourceFormat' => $projectCoverage->getSourceFormat()->name
                        ],
                        'Body' => json_encode($projectCoverage, JSON_THROW_ON_ERROR),
                    ]
                )
            );

            $response->resolve();

            return $response->info()['status'] === Response::HTTP_OK;
        } catch (HttpException $exception) {
            throw PersistException::from($exception);
        }
    }

    public function persistToBigQuery(Project $projectCoverage, string $uniqueId): bool
    {
        $table = $this->bigQueryClient->getLineAnalyticsDataset()
            ->table('lines');

        $insertResponse = $table->insertRows($this->buildRows($projectCoverage, $uniqueId));

        return $insertResponse->isSuccessful();
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
        $metadata = [
            [
                'key' => 'lineHits',
                'value' => $line->getLineHits(),
            ]
        ];

        if ($line instanceof BranchCoverage) {
            $metadata[] = [
                'key' => 'allBranchesHit',
                'value' => (string)empty(
                array_filter(
                    $line->getBranchHits(),
                    static fn(int $branchHits) => $branchHits === 0
                )
                )
            ];
        }

        return [
            'id' => $uniqueId,
            'sourceFormat' => $project->getSourceFormat()->name,
            'fileName' => $file->getFileName(),
            'generatedAt' => $project->getGeneratedAt() ?
                $project->getGeneratedAt()?->format('Y-m-d H:i:s') :
                null,
            'type' => $line->getType()->name,
            'lineNumber' => $line->getLineNumber(),
            'metadata' => $metadata
        ];
    }
}
