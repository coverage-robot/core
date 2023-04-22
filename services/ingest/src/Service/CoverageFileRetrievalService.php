<?php

namespace App\Service;

use App\Exception\RetrievalException;
use AsyncAws\Core\Exception\Exception;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\S3Client;
use Bref\Event\S3\Bucket;
use Bref\Event\S3\BucketObject;

class CoverageFileRetrievalService
{
    public function __construct(private readonly S3Client $s3Client)
    {
    }

    public function ingestFromS3(Bucket $bucket, BucketObject $object): string
    {
        try {
            $result = $this->s3Client->getObject(
                new GetObjectRequest(
                    [
                        'Bucket' => $bucket->getName(),
                        'Key' => $object->getKey(),
                    ]
                )
            );

            return $result->getBody()->getContentAsString();
        } catch (Exception $exception) {
            throw RetrievalException::from($exception);
        }
    }
}
