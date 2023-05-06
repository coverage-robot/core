<?php

namespace App\Tests\Service;

use App\Client\BigQueryClient;
use App\Exception\QueryException;
use App\Model\Upload;
use App\Query\TotalCommitCoverageQuery;
use App\Query\TotalCommitUploadsQuery;
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
                    TotalCommitCoverageQuery::class,
                    TotalCommitCoverageQuery::class === $query ? 'mock-query' : null,
                    ['mock-result']
                ),
                MockQueryFactory::createMock(
                    $this,
                    TotalCommitUploadsQuery::class,
                    TotalCommitUploadsQuery::class === $query ? 'mock-query' : null,
                    99
                ),
            ]
        );

        $mockQueryJobConfiguration = $this->createMock(QueryJobConfiguration::class);

        $mockBigQueryService->expects(!$expectedException ? $this->once() : $this->never())
            ->method("query")
            ->with("mock-query")
            ->willReturn($mockQueryJobConfiguration);

        $mockBigQueryService->expects(!$expectedException ? $this->once() : $this->never())
            ->method("runQuery")
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
                TotalCommitCoverageQuery::class,
                null
            ],
            'Total commit uploads query' => [
                TotalCommitUploadsQuery::class,
                null
            ],
            'Invalid query' => [
                'invalid-query',
                QueryException::class
            ]
        ];
    }
}
