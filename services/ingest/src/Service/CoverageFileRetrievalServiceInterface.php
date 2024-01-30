<?php

namespace App\Service;

use App\Exception\DeletionException;
use App\Exception\RetrievalException;
use AsyncAws\Core\Exception\Http\ClientException;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\S3\Exception\InvalidObjectStateException;
use AsyncAws\S3\Exception\NoSuchKeyException;
use AsyncAws\S3\Input\DeleteObjectRequest;
use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\Result\GetObjectOutput;
use AsyncAws\SimpleS3\SimpleS3Client;
use Bref\Event\S3\Bucket;
use Bref\Event\S3\BucketObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

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
