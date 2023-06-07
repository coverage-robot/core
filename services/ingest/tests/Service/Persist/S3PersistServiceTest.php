<?php

namespace App\Tests\Service\Persist;

use App\Exception\PersistException;
use App\Service\Persist\S3PersistService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\Result\PutObjectOutput;
use AsyncAws\S3\S3Client;
use DateTimeImmutable;
use Packages\Models\Enum\CoverageFormat;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Project;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class S3PersistServiceTest extends TestCase
{
    public function testPersist(): void
    {
        $coverage = new Project(
            CoverageFormat::CLOVER,
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
                            'sourceFormat' => $coverage->getSourceFormat()->value,
                            'commit' => '1',
                            'parent' => json_encode(['2']),
                            'ingestTime' => '2023-05-02T12:00:00+00:00',
                            'uploadId' => 'mock-uuid',
                            'provider' => 'github',
                            'owner' => 'mock-owner',
                            'repository' => 'mock-repo',
                            'ref' => 'mock-branch-reference',
                            'pullRequest' => 1234,
                            'tag' => 'backend'
                        ],
                        'Body' => json_encode($coverage, JSON_THROW_ON_ERROR),
                    ]
                )
            )
            ->willReturn(ResultMockFactory::createFailing(PutObjectOutput::class, 200));

        $S3PersistService = new S3PersistService(
            $mockS3Client,
            MockEnvironmentServiceFactory::getMock($this, Environment::DEVELOPMENT),
            new NullLogger()
        );
        $S3PersistService->persist(
            new Upload(
                'mock-uuid',
                Provider::GITHUB,
                'mock-owner',
                'mock-repo',
                '1',
                ['2'],
                'mock-branch-reference',
                1234,
                'backend',
                new DateTimeImmutable('2023-05-02 12:00:00')
            ),
            $coverage
        );
    }

    public function testFailingToPersist(): void
    {
        $coverage = new Project(
            CoverageFormat::CLOVER,
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
                            'sourceFormat' => $coverage->getSourceFormat()->value,
                            'commit' => '1',
                            'parent' => json_encode(['2']),
                            'ingestTime' => '2023-05-02T12:00:00+00:00',
                            'uploadId' => 'mock-uuid',
                            'provider' => 'github',
                            'owner' => 'mock-owner',
                            'repository' => 'mock-repo',
                            'ref' => 'mock-branch-reference',
                            'pullRequest' => 1234,
                            'tag' => 'backend'
                        ],
                        'Body' => json_encode($coverage, JSON_THROW_ON_ERROR),
                    ]
                )
            )
            ->willReturn(ResultMockFactory::createFailing(PutObjectOutput::class, 403));

        $this->expectException(PersistException::class);

        $S3PersistService = new S3PersistService(
            $mockS3Client,
            MockEnvironmentServiceFactory::getMock($this, Environment::DEVELOPMENT),
            new NullLogger()
        );
        $S3PersistService->persist(
            new Upload(
                'mock-uuid',
                Provider::GITHUB,
                'mock-owner',
                'mock-repo',
                '1',
                ['2'],
                'mock-branch-reference',
                1234,
                'backend',
                new DateTimeImmutable('2023-05-02 12:00:00')
            ),
            $coverage,
        );
    }
}
