<?php

namespace App\Tests\Service;

use App\Service\CoverageFileRetrievalService;
use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\Result\GetObjectOutput;
use AsyncAws\S3\S3Client;
use Bref\Event\S3\Bucket;
use Bref\Event\S3\BucketObject;
use PHPUnit\Framework\TestCase;

class CoverageFileRetrievalServiceTest extends TestCase
{
    public function testIngestFromS3(): void
    {
        $mockResponse = $this->createMock(GetObjectOutput::class);

        $mockS3Client = $this->createMock(S3Client::class);
        $mockS3Client->expects($this->once())
            ->method("getObject")
            ->with(
                new GetObjectRequest([
                    'Bucket' => "mock-bucket",
                    'Key' => "mock-key",
                ])
            )
            ->willReturn($mockResponse);

        $coverageFileRetrievalService = new CoverageFileRetrievalService($mockS3Client);

        $coverageFileRetrievalService->ingestFromS3(
            new Bucket("mock-bucket", "mock-arn"),
            new BucketObject("mock-key", 0)
        );
    }
}
