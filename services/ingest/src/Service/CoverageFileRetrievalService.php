<?php

namespace App\Service;

use App\Exception\RetrievalException;
use AsyncAws\Core\Exception\Exception;
use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\Result\GetObjectOutput;
use AsyncAws\S3\S3Client;
use Bref\Event\S3\Bucket;
use Bref\Event\S3\BucketObject;

class CoverageFileRetrievalService
{
    public function __construct(private readonly S3Client $s3Client)
    {
    }

    /**
     * Ingest a file from an S3 bucket.
     *
     * @param Bucket $bucket
     * @param BucketObject $object
     * @return GetObjectOutput
     */
    public function ingestFromS3(Bucket $bucket, BucketObject $object): GetObjectOutput
    {
        try {
            return $this->s3Client->getObject(
                new GetObjectRequest(
                    [
                        'Bucket' => $bucket->getName(),
                        'Key' => $object->getKey(),
                    ]
                )
            );
        } catch (Exception $exception) {
            throw RetrievalException::from($exception);
        }
    }
}
