<?php

namespace App\Tests\Service\Persist;

use App\Enum\CoverageFormatEnum;
use App\Enum\EnvironmentEnum;
use App\Exception\PersistException;
use App\Model\Project;
use App\Model\Upload;
use App\Service\Persist\S3PersistService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\Result\PutObjectOutput;
use AsyncAws\S3\S3Client;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class S3PersistServiceTest extends TestCase
{
    public function testPersist(): void
    {
        $coverage = new Project(
            CoverageFormatEnum::CLOVER,
            new DateTimeImmutable()
        );

        $mockS3Client = $this->createMock(S3Client::class);

        $mockS3Client->expects($this->once())
            ->method('putObject')
            ->with(
                new PutObjectRequest(
                    [
                        'Bucket' => 'coverage-output-dev',
                        'Key' => 'mock-uuid.json',
                        'ContentType' => 'application/json',
                        'Metadata' => [
                            'sourceFormat' => $coverage->getSourceFormat(),
                            'commit' => '1',
                            'parent' => '2',
                            'ingestTime' => new DateTimeImmutable('2023-05-02 12:00:00')
                        ],
                        'Body' => json_encode($coverage, JSON_THROW_ON_ERROR),
                    ]
                )
            )
            ->willReturn(ResultMockFactory::createFailing(PutObjectOutput::class, 200));

        $S3PersistService = new S3PersistService(
            $mockS3Client,
            MockEnvironmentServiceFactory::getMock($this, EnvironmentEnum::DEVELOPMENT),
            new NullLogger()
        );
        $S3PersistService->persist(
            new Upload(
                $coverage,
                'mock-uuid',
                '1',
                '2',
                new DateTimeImmutable('2023-05-02 12:00:00')
            )
        );
    }

    public function testFailingToPersist(): void
    {
        $coverage = new Project(
            CoverageFormatEnum::CLOVER,
            new DateTimeImmutable()
        );

        $mockS3Client = $this->createMock(S3Client::class);

        $mockS3Client->expects($this->once())
            ->method('putObject')
            ->with(
                new PutObjectRequest(
                    [
                        'Bucket' => 'coverage-output-dev',
                        'Key' => 'mock-uuid.json',
                        'ContentType' => 'application/json',
                        'Metadata' => [
                            'sourceFormat' => $coverage->getSourceFormat(),
                            'commit' => '1',
                            'parent' => '2',
                            'ingestTime' => new DateTimeImmutable('2023-05-02 12:00:00')
                        ],
                        'Body' => json_encode($coverage, JSON_THROW_ON_ERROR),
                    ]
                )
            )
            ->willReturn(ResultMockFactory::createFailing(PutObjectOutput::class, 403));

        $this->expectException(PersistException::class);

        $S3PersistService = new S3PersistService(
            $mockS3Client,
            MockEnvironmentServiceFactory::getMock($this, EnvironmentEnum::DEVELOPMENT),
            new NullLogger()
        );
        $S3PersistService->persist(
            new Upload(
                $coverage,
                'mock-uuid',
                '1',
                '2',
                new DateTimeImmutable('2023-05-02 12:00:00')
            )
        );
    }
}
