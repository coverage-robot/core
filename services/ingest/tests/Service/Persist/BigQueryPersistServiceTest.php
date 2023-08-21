<?php

namespace App\Tests\Service\Persist;

use App\Client\BigQueryClient;
use App\Enum\EnvironmentVariable;
use App\Service\BigQueryMetadataBuilderService;
use App\Service\Persist\BigQueryPersistService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Google\Cloud\BigQuery\Dataset;
use Google\Cloud\BigQuery\InsertResponse;
use Google\Cloud\BigQuery\Table;
use Packages\Models\Enum\CoverageFormat;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\LineType;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Coverage;
use Packages\Models\Model\File;
use Packages\Models\Model\Line\Branch;
use Packages\Models\Model\Line\Statement;
use Packages\Models\Model\Tag;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;

class BigQueryPersistServiceTest extends TestCase
{
    public function testPersist(): void
    {
        $fileCoverage = new File('mock-file');
        $fileCoverage->setLine(new Statement(1, 1));
        $fileCoverage->setLine(new Branch(2, 1, [0 => 0, 1 => 1]));

        $coverage = new Coverage(CoverageFormat::LCOV, 'mock/project/root');
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
            new Tag('mock-tag', '')
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
                    ],
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
                            'type' => LineType::BRANCH,
                            'lineNumber' => 2,
                            'metadata' => [
                                [
                                    'key' => 'type',
                                    'value' => LineType::BRANCH->value,
                                ],
                                [
                                    'key' => 'lineNumber',
                                    'value' => '2'
                                ],
                                [
                                    'key' => 'lineHits',
                                    'value' => '1'
                                ],
                                [
                                    'key' => 'branchHits',
                                    'value' => '[0,1]'
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

        $bigQueryPersistService = new BigQueryPersistService(
            $mockBigQueryClient,
            new BigQueryMetadataBuilderService(new NullLogger()),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-table'
                ]
            ),
            new NullLogger()
        );

        $bigQueryPersistService->persist($upload, $coverage);
    }
}
