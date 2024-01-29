<?php

namespace App\Tests\Service;

use App\Exception\DeletionException;
use App\Exception\RetrievalException;
use App\Service\CoverageFileRetrievalService;
use AsyncAws\Core\AwsError\AwsError;
use AsyncAws\Core\Response;
use AsyncAws\S3\Exception\NoSuchKeyException;
use AsyncAws\S3\Input\DeleteObjectRequest;
use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\Result\DeleteObjectOutput;
use AsyncAws\S3\Result\GetObjectOutput;
use AsyncAws\SimpleS3\SimpleS3Client;
use Bref\Event\S3\Bucket;
use Bref\Event\S3\BucketObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CoverageFileRetrievalServiceTest extends TestCase
{
    public function testIngestFromS3(): void
    {
        $mockS3Client = $this->createMock(SimpleS3Client::class);
        $mockS3Client->expects($this->once())
            ->method('getObject')
            ->with(
                new GetObjectRequest([
                    'Bucket' => 'mock-bucket',
                    'Key' => 'mock-key',
                ])
            )
            ->willReturn($this->createMock(GetObjectOutput::class));

        $coverageFileRetrievalService = new CoverageFileRetrievalService($mockS3Client, new NullLogger());

        $coverageFileRetrievalService->ingestFromS3(
            new Bucket('mock-bucket', 'mock-arn'),
            new BucketObject('mock-key', 0)
        );
    }

    public function testIngestingUnknownObjectFromS3(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getInfo')
            ->willReturn(\Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND);

        $mockS3Client = $this->createMock(SimpleS3Client::class);
        $mockS3Client->expects($this->once())
            ->method('getObject')
            ->with(
                new GetObjectRequest([
                    'Bucket' => 'mock-bucket',
                    'Key' => 'mock-key',
                ])
            )
            ->willThrowException(
                new NoSuchKeyException(
                    $mockResponse,
                    new AwsError(404, null, null, null)
                )
            );

        $this->expectException(RetrievalException::class);

        $coverageFileRetrievalService = new CoverageFileRetrievalService($mockS3Client, new NullLogger());

        $coverageFileRetrievalService->ingestFromS3(
            new Bucket('mock-bucket', 'mock-arn'),
            new BucketObject('mock-key', 0)
        );
    }

    public function testDeleteFromS3(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getInfo')
            ->willReturn(\Symfony\Component\HttpFoundation\Response::HTTP_NO_CONTENT);

        $mockS3Client = $this->createMock(SimpleS3Client::class);
        $mockS3Client->expects($this->once())
            ->method('deleteObject')
            ->with(
                new DeleteObjectRequest([
                    'Bucket' => 'mock-bucket',
                    'Key' => 'mock-key',
                ])
            )
            ->willReturn(
                new DeleteObjectOutput(
                    new Response(
                        $mockResponse,
                        $this->createMock(HttpClientInterface::class),
                        new NullLogger()
                    )
                )
            );

        $coverageFileRetrievalService = new CoverageFileRetrievalService($mockS3Client, new NullLogger());

        $success = $coverageFileRetrievalService->deleteFromS3(
            new Bucket('mock-bucket', 'mock-arn'),
            new BucketObject('mock-key', 0)
        );

        $this->assertTrue($success);
    }

    public function testFailingToDeleteFromS3(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getInfo')
            ->willReturn(\Symfony\Component\HttpFoundation\Response::HTTP_BAD_GATEWAY);

        $mockS3Client = $this->createMock(SimpleS3Client::class);
        $mockS3Client->expects($this->once())
            ->method('deleteObject')
            ->with(
                new DeleteObjectRequest([
                    'Bucket' => 'mock-bucket',
                    'Key' => 'mock-key',
                ])
            )
            ->willReturn(
                new DeleteObjectOutput(
                    new Response(
                        $mockResponse,
                        $this->createMock(HttpClientInterface::class),
                        new NullLogger()
                    )
                )
            );

        $coverageFileRetrievalService = new CoverageFileRetrievalService($mockS3Client, new NullLogger());

        $this->expectException(DeletionException::class);

        $coverageFileRetrievalService->deleteFromS3(
            new Bucket('mock-bucket', 'mock-arn'),
            new BucketObject('mock-key', 0)
        );
    }
}
