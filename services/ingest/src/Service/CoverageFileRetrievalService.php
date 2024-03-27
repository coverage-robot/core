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
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

final class CoverageFileRetrievalService implements CoverageFileRetrievalServiceInterface
{
    public function __construct(
        private readonly SimpleS3Client $s3Client,
        private readonly LoggerInterface $retrievalLogger
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws RetrievalException
     */
    #[Override]
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
     * @inheritDoc
     *
     * @throws DeletionException
     */
    #[Override]
    public function deleteFromS3(Bucket $bucket, BucketObject $object): bool
    {
        try {
            $response = $this->s3Client->deleteObject(
                new DeleteObjectRequest([
                    'Bucket' => $bucket->getName(),
                    'Key' => $object->getKey(),
                ])
            );

            $response->resolve();

            $statusCode = $response->info()['status'];

            if ($statusCode !== Response::HTTP_NO_CONTENT) {
                throw new DeletionException(
                    sprintf(
                        'Non-successful HTTP code (%s) returned when deleting ingested file.',
                        $statusCode
                    )
                );
            }
        } catch (
            NoSuchKeyException |
            InvalidObjectStateException |
            ClientException |
            HttpException |
            DeletionException $exception
        ) {
            $this->retrievalLogger->error(
                'Failed to delete ingested file.',
                [
                    'exception' => $exception,
                    'bucket' => $bucket->getName(),
                    'key' => $object->getKey(),
                ]
            );

            throw DeletionException::from($exception);
        }

        return true;
    }
}
