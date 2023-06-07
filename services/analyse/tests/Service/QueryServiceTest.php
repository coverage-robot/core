<?php

namespace App\Tests\Service;

use App\Client\BigQueryClient;
use App\Exception\QueryException;
use App\Model\QueryResult\IntegerQueryResult;
use App\Model\QueryResult\QueryResultInterface;
use App\Model\QueryResult\TotalCoverageQueryResult;
use App\Model\QueryResult\TotalTagCoverageQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalTagCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\QueryService;
use App\Tests\Mock\Factory\MockQueryFactory;
use Google\Cloud\BigQuery\QueryJobConfiguration;
use Google\Cloud\BigQuery\QueryResults;
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
                        $queryResult : $this->createMock(TotalCoverageQueryResult::class)
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
                        $queryResult : $this->createMock(TotalTagCoverageQueryResult::class)
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
                    $this->createMock(TotalCoverageQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    TotalUploadsQuery::class,
                    null,
                    $this->createMock(IntegerQueryResult::class)
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
            'Total commit coverage query' => [
                TotalCoverageQuery::class,
                TotalCoverageQueryResult::from([
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
        ];
    }
}
