<?php

namespace Packages\Clients\Tests\Service;

use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\Result\PutObjectOutput;
use AsyncAws\S3\S3Client;
use Packages\Clients\Service\ObjectReferenceService;
use Packages\Clients\Tests\Client\ObjectReferenceClient;
use Packages\Clients\Tests\Client\ObjectReferenceClientInterface;
use Packages\Clients\Tests\Client\S3ClientInterface;
use PHPUnit\Framework\TestCase;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Environment\Service;
use Psr\Log\NullLogger;

class ObjectReferenceServiceTest extends TestCase
{
    public function testCreateReference(): void
    {
        $mockEnvironmentService = $this->createMock(EnvironmentServiceInterface::class);
        $mockEnvironmentService->method('getService')
            ->willReturn(Service::API);

        $mockS3Client = $this->createMock(ObjectReferenceClientInterface::class);
        $mockS3Client->expects($this->once())
            ->method('putObject')
            ->with($this->callback(function (PutObjectRequest $input) {
                return $input->getBucket() === 'object_reference_store_name'
                    && $input->getKey() !== null
                    && $input->getBody() !== null
                    && $input->getMetadata()['service'] === Service::API->value;
            }))
            ->willReturn($this->createMock(PutObjectOutput::class));

        $mockS3Client->expects($this->once())
            ->method('presign')
            ->willReturn('https://example.com');

        $objectReferenceService = new ObjectReferenceService(
            'object_reference_store_name',
            $mockS3Client,
            $mockEnvironmentService,
            new NullLogger()
        );

        $reference = $objectReferenceService->createReference('content');

        $this->assertStringStartsWith(sprintf('%s/', Service::API->value), $reference->getPath());
        $this->assertNotNull($reference->getSignedUrl());
    }
}
