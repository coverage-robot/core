<?php

namespace App\Tests\Service;

use App\Service\CoverageFileRetrievalService;
use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\Result\GetObjectOutput;
use AsyncAws\S3\S3Client;
use Bref\Event\S3\Bucket;
use Bref\Event\S3\BucketObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CoverageFileRetrievalServiceTest extends TestCase
{
    public function testIngestFromS3(): void
    {
        $mockS3Client = $this->createMock(S3Client::class);
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
}
