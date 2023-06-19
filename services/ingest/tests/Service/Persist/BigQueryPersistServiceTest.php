<?php

namespace App\Tests\Service\Persist;

use App\Client\BigQueryClient;
use App\Service\Persist\BigQueryPersistService;
use Google\Cloud\BigQuery\Dataset;
use Google\Cloud\BigQuery\InsertResponse;
use Google\Cloud\BigQuery\Table;
use Packages\Models\Enum\CoverageFormat;
use Packages\Models\Enum\LineType;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\File;
use Packages\Models\Model\Line\StatementCoverage;
use Packages\Models\Model\Project;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;

class BigQueryPersistServiceTest extends TestCase
{
    public function testPersist(): void
    {
        $fileCoverage = new File('mock-file');
        $fileCoverage->setLineCoverage(new StatementCoverage(1, 1));

        $coverage = new Project(CoverageFormat::LCOV, 'mock/project/root');
        $coverage->addFile($fileCoverage);

        $upload = new Upload(
            Uuid::uuid4()->toString(),
            Provider::GITHUB,
            '',
            '',
            '',
            [],
            'mock-branch-reference',
            1,
            'mock-tag'
        );

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
                            'uploadId' => $upload->getUploadId(),
                            'commit' => '',
                            'parent' => [],
                            'provider' => 'github',
                            'owner' => '',
                            'repository' => '',
                            'ref' => 'mock-branch-reference',
                            'ingestTime' => $upload->getIngestTime()->format('Y-m-d H:i:s'),
                            'sourceFormat' => CoverageFormat::LCOV,
                            'fileName' => 'mock-file',
                            'generatedAt' => null,
                            'type' => LineType::STATEMENT,
                            'lineNumber' => 1,
                            'metadata' => [
                                [
                                    'key' => 'type',
                                    'value' => LineType::STATEMENT->value,
                                ],
                                [
                                    'key' => 'lineNumber',
                                    'value' => '1'
                                ],
                                [
                                    'key' => 'lineHits',
                                    'value' => '1'
                                ]
                            ],
                            'tag' => 'mock-tag'
                        ]
                    ]
                ]
            )
            ->willReturn($insertResponse);

        $mockBigQueryDataset = $this->createMock(Dataset::class);
        $mockBigQueryDataset->expects($this->once())
            ->method('table')
            ->with('mock-table')
            ->willReturn($mockTable);

        $mockBigQueryClient = $this->createMock(BigQueryClient::class);
        $mockBigQueryClient->expects($this->once())
            ->method('getEnvironmentDataset')
            ->willReturn($mockBigQueryDataset);

        $bigQueryPersistService = new BigQueryPersistService($mockBigQueryClient, new NullLogger());

        $bigQueryPersistService->persist($upload, $coverage);
    }
}
