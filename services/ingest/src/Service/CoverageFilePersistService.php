<?php

namespace App\Service;

use App\Exception\PersistException;
use App\Model\ProjectCoverage;
use AsyncAws\Core\Exception\Exception;
use AsyncAws\Core\Exception\Http\ClientException;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use Symfony\Component\HttpFoundation\Response;

class CoverageFilePersistService
{
    public function __construct(private readonly S3Client $s3Client)
    {
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
                        "Bucket" => $bucket,
                        "Key" => $key,
                        "ContentType" => "application/json",
                        "Metadata" => [
                            "sourceFormat" => $projectCoverage->getSourceFormat()->name
                        ],
                        "Body" => json_encode($projectCoverage, JSON_THROW_ON_ERROR),
                    ]
                )
            );

            $response->resolve();

            return $response->info()["status"] === Response::HTTP_OK;
        }
        catch (HttpException $exception) {
            throw PersistException::from($exception);
        }
    }
}
