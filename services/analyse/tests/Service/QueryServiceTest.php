<?php

namespace App\Tests\Service;

use App\Client\BigQueryClient;
use App\Exception\QueryException;
use App\Query\LineCoverageQuery;
use App\Query\Result\CoverageQueryResult;
use App\Query\Result\IntegerQueryResult;
use App\Query\Result\MultiLineCoverageQueryResult;
use App\Query\Result\MultiTagCoverageQueryResult;
use App\Query\Result\QueryResultInterface;
use App\Query\TotalCoverageQuery;
use App\Query\TotalTagCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\QueryService;
use App\Tests\Mock\Factory\MockQueryFactory;
use Google\Cloud\BigQuery\QueryJobConfiguration;
use Google\Cloud\BigQuery\QueryResults;
use Packages\Models\Enum\LineState;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QueryServiceTest extends TestCase
{
    #[DataProvider('queryDataProvider')]
    public function testRunQuery(string $query, QueryResultInterface $queryResult): void
    {
        $mockBigQueryService = $this->createMock(BigQueryClient::class);

        $queryService = new QueryService(
            $mockBigQueryService,
            [
                MockQueryFactory::createMock(
                    $this,
                    TotalCoverageQuery::class,
                    TotalCoverageQuery::class,
                    TotalCoverageQuery::class === $query ?
                        $queryResult : $this->createMock(CoverageQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    TotalUploadsQuery::class,
                    TotalUploadsQuery::class,
                    TotalUploadsQuery::class === $query ? $queryResult : $this->createMock(IntegerQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    TotalTagCoverageQuery::class,
                    TotalTagCoverageQuery::class,
                    TotalTagCoverageQuery::class === $query ?
                        $queryResult : $this->createMock(MultiTagCoverageQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    LineCoverageQuery::class,
                    LineCoverageQuery::class,
                    LineCoverageQuery::class === $query ?
                        $queryResult : $this->createMock(MultiLineCoverageQueryResult::class)
                )
            ]
        );

        $mockQueryJobConfiguration = $this->createMock(QueryJobConfiguration::class);

        $mockBigQueryService->expects($this->once())
            ->method('query')
            ->with($query)
            ->willReturn($mockQueryJobConfiguration);

        $mockBigQueryService->expects($this->once())
            ->method('runQuery')
            ->with($mockQueryJobConfiguration)
            ->willReturn($this->createMock(QueryResults::class));

        $result = $queryService->runQuery($query, $this->createMock(Upload::class));

        $this->assertEquals($queryResult, $result);
    }

    public function testRunInvalidQuery(): void
    {
        $mockBigQueryService = $this->createMock(BigQueryClient::class);

        $queryService = new QueryService(
            $mockBigQueryService,
            [
                MockQueryFactory::createMock(
                    $this,
                    TotalCoverageQuery::class,
                    null,
                    $this->createMock(CoverageQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    TotalUploadsQuery::class,
                    null,
                    $this->createMock(IntegerQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    LineCoverageQuery::class,
                    null,
                    $this->createMock(MultiLineCoverageQueryResult::class)
                ),
            ]
        );

        $mockQueryJobConfiguration = $this->createMock(QueryJobConfiguration::class);

        $mockBigQueryService->expects($this->never())
            ->method('query')
            ->with('mock-query')
            ->willReturn($mockQueryJobConfiguration);

        $mockBigQueryService->expects($this->never())
            ->method('runQuery')
            ->with($mockQueryJobConfiguration)
            ->willReturn($this->createMock(QueryResults::class));

        $this->expectException(QueryException::class);

        $queryService->runQuery('invalid-query', $this->createMock(Upload::class));
    }

    public static function queryDataProvider(): array
    {
        return [
            'Total coverage query' => [
                TotalCoverageQuery::class,
                CoverageQueryResult::from([
                    'lines' => 6,
                    'covered' => 1,
                    'partial' => 2,
                    'uncovered' => 3,
                    'coveragePercentage' => 0
                ])
            ],
            'Total commit uploads query' => [
                TotalUploadsQuery::class,
                IntegerQueryResult::from(99)
            ],
            'Diff coverage query' => [
                LineCoverageQuery::class,
                MultiLineCoverageQueryResult::from([
                    [
                        'fileName' => 'test-file-1',
                        'lineNumber' => 1,
                        'state' => LineState::UNCOVERED->value
                    ],
                    [
                        'fileName' => 'test-file-2',
                        'lineNumber' => 2,
                        'state' => LineState::COVERED->value
                    ],
                ])
            ],
        ];
    }
}
