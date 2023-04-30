<?php

namespace App\Tests\Service\Persist;

use App\Client\BigQueryClient;
use App\Enum\CoverageFormatEnum;
use App\Enum\LineTypeEnum;
use App\Model\File;
use App\Model\Line\StatementCoverage;
use App\Model\Project;
use App\Service\Persist\BigQueryPersistService;
use Google\Cloud\BigQuery\Dataset;
use Google\Cloud\BigQuery\InsertResponse;
use Google\Cloud\BigQuery\Table;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;

class BigQueryPersistServiceTest extends TestCase
{
    public function testPersist(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $fileCoverage = new File('mock-file');
        $fileCoverage->setLineCoverage(new StatementCoverage(1, 1));

        $coverage = new Project(CoverageFormatEnum::LCOV);
        $coverage->addFile($fileCoverage);

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
                            'type' => LineTypeEnum::STATEMENT->name,
                            'lineNumber' => 1,
                            'metadata' => [
                                [
                                    'key' => 'type',
                                    'value' => LineTypeEnum::STATEMENT->name,
                                ],
                                [
                                    'key' => 'lineNumber',
                                    'value' => '1'
                                ],
                                [
                                    'key' => 'lineHits',
                                    'value' => '1'
                                ]
                            ]
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

        $bigQueryPersistService = new BigQueryPersistService($mockBigQueryClient, new NullLogger());

        $bigQueryPersistService->persist($coverage, $uuid);
    }
}
