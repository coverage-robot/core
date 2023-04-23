<?php

namespace App\Service;

use App\Client\BigQueryClient;
use App\Exception\PersistException;
use App\Model\FileCoverage;
use App\Model\LineCoverage;
use App\Model\ProjectCoverage;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use Symfony\Component\HttpFoundation\Response;

class CoverageFilePersistService
{
    public function __construct(
        private readonly S3Client $s3Client,
        private readonly BigQueryClient $bigQueryClient
    ) {
    }

    /**
     * @throws \JsonException
     */
    public function persistToS3(string $bucket, string $key, ProjectCoverage $projectCoverage): bool
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

    public function persistToBigQuery(ProjectCoverage $projectCoverage, string $uniqueId): bool
    {
        $table = $this->bigQueryClient->getLineAnalyticsDataset()
            ->table('lines');

        $insertResponse = $table->insertRows($this->buildRows($projectCoverage, $uniqueId));

        return $insertResponse->isSuccessful();
    }

    private function buildRows(ProjectCoverage $projectCoverage, string $uniqueId): array
    {
        return array_reduce(
            $projectCoverage->getFileCoverage(),
            static function (array $carry, FileCoverage $fileCoverage) use ($projectCoverage, $uniqueId): array {
                return [
                    ...$carry,
                    ...array_map(
                        static fn(LineCoverage $lineCoverage): array => [
                            'data' => [
                                'id' => $uniqueId,
                                'sourceFormat' => $projectCoverage->getSourceFormat()->name,
                                'fileName' => $fileCoverage->getFileName(),
                                'generatedAt' => $projectCoverage->getGeneratedAt() ?
                                    $projectCoverage->getGeneratedAt()?->format('Y-m-d H:i:s') :
                                    null,
                                'lineNumber' => $lineCoverage->getLineNumber(),
                                'lineHits' => $lineCoverage->getLineHits()
                            ]
                        ],
                        $fileCoverage->getLineCoverage()
                    )
                ];
            },
            []
        );
    }
}
