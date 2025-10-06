<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\DeletionException;
use App\Exception\RetrievalException;
use AsyncAws\S3\Result\GetObjectOutput;
use Bref\Event\S3\Bucket;
use Bref\Event\S3\BucketObject;

interface CoverageFileRetrievalServiceInterface
{
    /**
     * Ingest a file from an S3 bucket.
     *
     * @throws RetrievalException
     */
    public function ingestFromS3(Bucket $bucket, BucketObject $object): GetObjectOutput;

    /**
     * Mark an ingested file as deleted in S3 so that it can be cleaned up later.
     *
     * There's versioning and a lifecycle policy on the buckets, meaning the files
     * will still remain for a short period (days) after deletion, with a delete marker.
     *
     * @throws DeletionException
     */
    public function deleteFromS3(Bucket $bucket, BucketObject $object): bool;
}
