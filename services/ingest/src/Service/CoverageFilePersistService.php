<?php

namespace App\Service;

use App\Model\ProjectCoverage;
use AsyncAws\S3\Enum\ObjectCannedACL;
use AsyncAws\S3\Enum\ServerSideEncryption;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;

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
        $this->s3Client->putObject(new PutObjectRequest(
            [
                "Bucket" => $bucket,
                "Key" => $key,
                "ContentType" => "application/json",
                "Metadata" => [
                    "sourceFormat" => $projectCoverage->getSourceFormat()->name
                ],
                "Body" => json_encode($projectCoverage, JSON_THROW_ON_ERROR),
            ]
        ));

        return true;
    }
}