<?php

namespace App\Tests\Service;

use App\Client\BigQueryClient;
use App\Exception\QueryException;
use App\Model\QueryResult\IntegerQueryResult;
use App\Model\QueryResult\TotalCoverageQueryResult;
use App\Model\Upload;
use App\Query\TotalCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\QueryService;
use App\Tests\Mock\Factory\MockQueryFactory;
use Google\Cloud\BigQuery\QueryJobConfiguration;
use Google\Cloud\BigQuery\QueryResults;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QueryServiceTest extends TestCase
{
    #[DataProvider('queryDataProvider')]
    public function testRunQuery(string $query, ?string $expectedException): void
    {
        $mockBigQueryService = $this->createMock(BigQueryClient::class);

        $queryService = new QueryService(
            $mockBigQueryService,
            [
                MockQueryFactory::createMock(
                    $this,
                    TotalCoverageQuery::class,
                    TotalCoverageQuery::class === $query ? 'mock-query' : null,
                    TotalCoverageQueryResult::from([
                        'lines' => 6,
                        'covered' => 1,
                        'partial' => 2,
                        'uncovered' => 3,
                        'coveragePercentage' => 0
                    ])
                ),
                MockQueryFactory::createMock(
                    $this,
                    TotalUploadsQuery::class,
                    TotalUploadsQuery::class === $query ? 'mock-query' : null,
                    IntegerQueryResult::from(99)
                ),
            ]
        );

        $mockQueryJobConfiguration = $this->createMock(QueryJobConfiguration::class);

        $mockBigQueryService->expects(!$expectedException ? $this->once() : $this->never())
            ->method('query')
            ->with('mock-query')
            ->willReturn($mockQueryJobConfiguration);

        $mockBigQueryService->expects(!$expectedException ? $this->once() : $this->never())
            ->method('runQuery')
            ->with($mockQueryJobConfiguration)
            ->willReturn($this->createMock(QueryResults::class));

        if ($expectedException) {
            $this->expectException($expectedException);
        }

        $queryService->runQuery($query, $this->createMock(Upload::class));
    }

    public static function queryDataProvider(): array
    {
        return [
            'Total commit coverage query' => [
                TotalCoverageQuery::class,
                null
            ],
            'Total commit uploads query' => [
                TotalUploadsQuery::class,
                null
            ],
            'Invalid query' => [
                'invalid-query',
                QueryException::class
            ]
        ];
    }
}
