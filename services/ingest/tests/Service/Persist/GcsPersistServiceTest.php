<?php

namespace App\Tests\Service\Persist;

use App\Client\BigQueryClient;
use App\Client\GoogleCloudStorageClient;
use App\Enum\EnvironmentVariable;
use App\Service\BigQueryMetadataBuilderService;
use App\Service\Persist\GcsPersistService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Google\Cloud\BigQuery\Dataset;
use Google\Cloud\BigQuery\InsertResponse;
use Google\Cloud\BigQuery\Job;
use Google\Cloud\BigQuery\LoadJobConfiguration;
use Google\Cloud\BigQuery\Table;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageObject;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\Upload;
use Packages\Models\Enum\CoverageFormat;
use Packages\Contracts\Environment\Environment;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\Tag;
use Packages\Telemetry\Service\MetricService;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GcsPersistServiceTest extends KernelTestCase
{
    public function testPersistSuccessfully(): void
    {
        $mockBucket = $this->createMock(Bucket::class);
        $mockStorageObject = $this->createMock(StorageObject::class);

        $mockGcsClient = $this->createMock(GoogleCloudStorageClient::class);
        $mockGcsClient->expects($this->once())
            ->method('bucket')
            ->with(
                sprintf(
                    GcsPersistService::OUTPUT_BUCKET,
                    Environment::DEVELOPMENT->value
                )
            )
            ->willReturn($mockBucket);

        $mockBucket->expects($this->once())
            ->method('upload')
            ->willReturn($mockStorageObject);

        $mockDataset = $this->createMock(Dataset::class);
        $mockTable = $this->createMock(Table::class);

        $mockDataset->expects($this->exactly(2))
            ->method('table')
            ->willReturn($mockTable);
        $mockTable->expects($this->once())
            ->method('loadFromStorage')
            ->with($mockStorageObject)
            ->willReturn($this->createMock(LoadJobConfiguration::class));
        $mockTable->expects($this->once())
            ->method('insertRow')
            ->willReturn(new InsertResponse([], []));

        $mockBigQueryClient = $this->createMock(BigQueryClient::class);
        $mockBigQueryClient->expects($this->exactly(2))
            ->method('getEnvironmentDataset')
            ->willReturn($mockDataset);

        $mockJob = $this->createMock(Job::class);
        $mockJob->method('id')
            ->willReturn('mock-job-id');
        $mockJob->expects($this->once())
            ->method('isComplete')
            ->willReturn(true);

        $mockBigQueryClient->expects($this->once())
            ->method('runJob')
            ->willReturn($mockJob);

        $gcsPersistService = new GcsPersistService(
            $mockGcsClient,
            $mockBigQueryClient,
            $this->getContainer()->get(BigQueryMetadataBuilderService::class),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::DEVELOPMENT,
                [
                    EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-line-coverage-table',
                    EnvironmentVariable::BIGQUERY_UPLOAD_TABLE->value => 'mock-upload-table'
                ]
            ),
            new NullLogger(),
            $this->createMock(MetricService::class)
        );

        $this->assertTrue(
            $gcsPersistService->persist(
                new Upload(
                    Uuid::uuid4()->toString(),
                    Provider::GITHUB,
                    '',
                    '',
                    '',
                    [],
                    'mock-branch-reference',
                    'project/root',
                    1,
                    new Tag('mock-tag', '')
                ),
                new Coverage(CoverageFormat::LCOV, 'mock/project/root')
            )
        );
    }

    public function testPersistFailsLineCoverageUpload(): void
    {
        $mockBucket = $this->createMock(Bucket::class);
        $mockStorageObject = $this->createMock(StorageObject::class);

        $mockGcsClient = $this->createMock(GoogleCloudStorageClient::class);
        $mockGcsClient->expects($this->once())
            ->method('bucket')
            ->with(
                sprintf(
                    GcsPersistService::OUTPUT_BUCKET,
                    Environment::DEVELOPMENT->value
                )
            )
            ->willReturn($mockBucket);

        $mockBucket->expects($this->once())
            ->method('upload')
            ->willReturn($mockStorageObject);

        $mockDataset = $this->createMock(Dataset::class);
        $mockTable = $this->createMock(Table::class);

        $mockDataset->expects($this->once())
            ->method('table')
            ->willReturn($mockTable);
        $mockTable->expects($this->once())
            ->method('loadFromStorage')
            ->with($mockStorageObject)
            ->willReturn($this->createMock(LoadJobConfiguration::class));
        $mockTable->expects($this->never())
            ->method('insertRow');

        $mockBigQueryClient = $this->createMock(BigQueryClient::class);
        $mockBigQueryClient->expects($this->once())
            ->method('getEnvironmentDataset')
            ->willReturn($mockDataset);

        $mockJob = $this->createMock(Job::class);
        $mockJob->method('id')
            ->willReturn('mock-job-id');
        $mockJob->expects($this->once())
            ->method('isComplete')
            ->willReturn(true);
        $mockJob->method('info')
            ->willReturn(['status' => ['errorResult' => ['message' => 'mock-error-message']]]);

        $mockBigQueryClient->expects($this->once())
            ->method('runJob')
            ->willReturn($mockJob);

        $gcsPersistService = new GcsPersistService(
            $mockGcsClient,
            $mockBigQueryClient,
            $this->getContainer()->get(BigQueryMetadataBuilderService::class),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::DEVELOPMENT,
                [
                    EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-line-coverage-table'
                ]
            ),
            new NullLogger(),
            $this->createMock(MetricService::class)
        );

        $this->assertFalse(
            $gcsPersistService->persist(
                new Upload(
                    Uuid::uuid4()->toString(),
                    Provider::GITHUB,
                    '',
                    '',
                    '',
                    [],
                    'mock-branch-reference',
                    'project/root',
                    1,
                    new Tag('mock-tag', '')
                ),
                new Coverage(CoverageFormat::LCOV, 'mock/project/root')
            )
        );
    }
}
