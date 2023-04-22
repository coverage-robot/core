<?php

namespace App\Tests\Service;

use App\Enum\CoverageFormatEnum;
use App\Exception\PersistException;
use App\Model\ProjectCoverage;
use App\Service\CoverageFilePersistService;
use AsyncAws\Core\Result;
use AsyncAws\Core\Test\Http\SimpleMockedResponse;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\Result\PutObjectOutput;
use AsyncAws\S3\S3Client;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class CoverageFilePersistServiceTest extends TestCase
{
    public function testPersistToS3(): void
    {
        $coverage = new ProjectCoverage(
            CoverageFormatEnum::CLOVER,
            new DateTimeImmutable()
        );

        $mockS3Client = $this->createMock(S3Client::class);

        $mockS3Client->expects($this->once())
            ->method('putObject')
            ->with(
                new PutObjectRequest(
                    [
                       'Bucket' => 'mock-bucket',
                       'Key' => 'mock-object.json',
                       'ContentType' => 'application/json',
                       'Metadata' => [
                           'sourceFormat' => $coverage->getSourceFormat()->name
                       ],
                       'Body' => json_encode($coverage, JSON_THROW_ON_ERROR),
                    ]
                )
            )
            ->willReturn(ResultMockFactory::createFailing(PutObjectOutput::class, 200));

        $coverageFilePersistService = new CoverageFilePersistService($mockS3Client);
        $coverageFilePersistService->persistToS3(
            'mock-bucket',
            'mock-object.json',
            $coverage
        );
    }

    public function testFailingToPersistToS3(): void
    {
        $coverage = new ProjectCoverage(
            CoverageFormatEnum::CLOVER,
            new DateTimeImmutable()
        );

        $mockS3Client = $this->createMock(S3Client::class);

        $mockS3Client->expects($this->once())
            ->method('putObject')
            ->with(
                new PutObjectRequest(
                    [
                        'Bucket' => 'mock-bucket',
                        'Key' => 'mock-object.json',
                        'ContentType' => 'application/json',
                        'Metadata' => [
                            'sourceFormat' => $coverage->getSourceFormat()->name
                        ],
                        'Body' => json_encode($coverage, JSON_THROW_ON_ERROR),
                    ]
                )
            )
            ->willReturn(ResultMockFactory::createFailing(PutObjectOutput::class, 403));

        $this->expectException(PersistException::class);

        $coverageFilePersistService = new CoverageFilePersistService($mockS3Client);
        $coverageFilePersistService->persistToS3(
            'mock-bucket',
            'mock-object.json',
            $coverage
        );
    }
}
