<?php

namespace App\Service;

use Bref\Event\S3\Bucket;
use Bref\Event\S3\BucketObject;

class CoverageFileRetrievalService
{
    public function ingestFromS3(Bucket $bucket, BucketObject $object): string
    {
        return file_get_contents('./coverage.xml');
    }
}
