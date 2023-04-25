<?php

namespace App\Tests\Service;

use App\Client\BigQueryClient;
use App\Enum\CoverageFormatEnum;
use App\Enum\LineTypeEnum;
use App\Exception\PersistException;
use App\Model\FileCoverage;
use App\Model\LineCoverage;
use App\Model\ProjectCoverage;
use App\Service\CoverageFilePersistService;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\Result\PutObjectOutput;
use AsyncAws\S3\S3Client;
use DateTimeImmutable;
use Google\Cloud\BigQuery\Dataset;
use Google\Cloud\BigQuery\InsertResponse;
use Google\Cloud\BigQuery\Table;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

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

        $coverageFilePersistService = new CoverageFilePersistService(
            $mockS3Client,
            $this->createMock(BigQueryClient::class)
        );
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

        $coverageFilePersistService = new CoverageFilePersistService(
            $mockS3Client,
            $this->createMock(BigQueryClient::class)
        );
        $coverageFilePersistService->persistToS3(
            'mock-bucket',
            'mock-object.json',
            $coverage
        );
    }

    public function testPersistToBigQuery(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $fileCoverage = new FileCoverage('mock-file');
        $fileCoverage->addLineCoverage(new LineCoverage(LineTypeEnum::UNKNOWN, 1, null, 1));

        $coverage = new ProjectCoverage(CoverageFormatEnum::LCOV);
        $coverage->addFileCoverage($fileCoverage);

        $insertResponse = $this->createMock(InsertResponse::class);
        $insertResponse->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(true);

        $mockTable = $this->createMock(Table::class);
        $mockTable->expects($this->once())
            ->method('insertRows')
            ->with(
                [
                    [
                        'data' => [
                            'id' => $uuid,
                            'sourceFormat' => 'LCOV',
                            'fileName' => 'mock-file',
                            'generatedAt' => null,
                            'lineNumber' => 1,
                            'lineHits' => 1
                        ]
                    ]
                ]
            )
            ->willReturn($insertResponse);

        $mockBigQueryDataset = $this->createMock(Dataset::class);
        $mockBigQueryDataset->expects($this->once())
            ->method('table')
            ->with('lines')
            ->willReturn($mockTable);

        $mockBigQueryClient = $this->createMock(BigQueryClient::class);
        $mockBigQueryClient->expects($this->once())
            ->method('getLineAnalyticsDataset')
            ->willReturn($mockBigQueryDataset);

        $coverageFilePersistService = new CoverageFilePersistService(
            $this->createMock(S3Client::class),
            $mockBigQueryClient
        );

        $coverageFilePersistService->persistToBigQuery($coverage, $uuid);
    }
}
