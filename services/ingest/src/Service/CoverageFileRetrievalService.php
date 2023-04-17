<?php

namespace App\Service;

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
        $result = $this->s3Client->getObject([
            'Bucket' => $bucket->getName(),
            'Key' => $object->getKey(),
        ]);

        return $result->getBody()->getContentAsString();
    }
}
