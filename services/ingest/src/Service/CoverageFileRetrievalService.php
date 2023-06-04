<?php

namespace App\Service;

use App\Exception\RetrievalException;
use AsyncAws\S3\Exception\InvalidObjectStateException;
use AsyncAws\S3\Exception\NoSuchKeyException;
use AsyncAws\S3\Input\DeleteObjectRequest;
use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\Result\GetObjectOutput;
use AsyncAws\S3\S3Client;
use Bref\Event\S3\Bucket;
use Bref\Event\S3\BucketObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class CoverageFileRetrievalService
{
    public function __construct(
        private readonly S3Client $s3Client,
        private readonly LoggerInterface $retrievalLogger
    ) {
    }

    /**
     * Ingest a file from an S3 bucket.
     */
    public function ingestFromS3(Bucket $bucket, BucketObject $object): GetObjectOutput
    {
        try {
            // Retrieve the coverage file from S3 and store the contents in memory.
            $coverageFile = $this->s3Client->getObject(
                new GetObjectRequest(
                    [
                        'Bucket' => $bucket->getName(),
                        'Key' => $object->getKey(),
                    ]
                )
            );

            return $coverageFile;
        } catch (NoSuchKeyException | InvalidObjectStateException $exception) {
            throw RetrievalException::from($exception);
        }
    }

    /**
     * Mark an ingested file as deleted in S3 so that it can be cleaned up later. There's
     * versioning and a lifecycle policy on the buckets, meaning the files will still remain
     * for a short period (days) after deletion, with a delete marker.
     */
    public function deleteIngestedFile(Bucket $bucket, BucketObject $object): bool
    {
        try {
            $response = $this->s3Client->deleteObject(
                new DeleteObjectRequest([
                    'Bucket' => $bucket->getName(),
                    'Key' => $object->getKey(),
                ])
            );

            if ($response->info()['status'] !== Response::HTTP_OK) {
                $this->retrievalLogger->warning(
                    'Non-successful HTTP code returned when attempting to delete ingested file.',
                    [
                        'status' => $response->info()['status'],
                        'bucket' => $bucket->getName(),
                        'key' => $object->getKey(),
                    ]
                );

                return false;
            }

            return true;
        } catch (NoSuchKeyException | InvalidObjectStateException $exception) {
            $this->retrievalLogger->error(
                'Failed to delete ingested file.',
                [
                    'exception' => $exception,
                    'bucket' => $bucket->getName(),
                    'key' => $object->getKey(),
                ]
            );

            return false;
        }
    }
}
