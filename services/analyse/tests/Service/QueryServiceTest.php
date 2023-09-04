<?php

namespace App\Tests\Service;

use App\Client\BigQueryClient;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\LineCoverageQuery;
use App\Query\Result\CoverageQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\QueryResultInterface;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalTagCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\QueryBuilderService;
use App\Service\QueryService;
use App\Tests\Mock\Factory\MockQueryFactory;
use Google\Cloud\BigQuery\QueryJobConfiguration;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Enum\LineState;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class QueryServiceTest extends TestCase
{
    #[DataProvider('queryDataProvider')]
    public function testRunQuery(string $query, QueryResultInterface $queryResult): void
    {
        $mockBigQueryService = $this->createMock(BigQueryClient::class);

        $mockQueryBuilderService = $this->createMock(QueryBuilderService::class);

        $queryService = new QueryService(
            $mockBigQueryService,
            [
                MockQueryFactory::createMock(
                    $this,
                    TotalCoverageQuery::class,
                    '',
                    TotalCoverageQuery::class === $query ? $queryResult :
                        $this->createMock(CoverageQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    TotalUploadsQuery::class,
                    '',
                    TotalUploadsQuery::class === $query ? $queryResult :
                        $this->createMock(TotalUploadsQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    TotalTagCoverageQuery::class,
                    '',
                    TotalTagCoverageQuery::class === $query ? $queryResult :
                        $this->createMock(TagCoverageCollectionQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    LineCoverageQuery::class,
                    '',
                    LineCoverageQuery::class === $query ? $queryResult :
                        $this->createMock(LineCoverageCollectionQueryResult::class)
                )
            ],
            $mockQueryBuilderService,
            new NullLogger()
        );

        $mockQueryJobConfiguration = $this->createMock(QueryJobConfiguration::class);

        $mockQueryBuilderService->expects($this->once())
            ->method('build')
            ->with($this->isInstanceOf($query))
            ->willReturn('formatted-query');

        $mockBigQueryService->expects($this->once())
            ->method('query')
            ->with('formatted-query')
            ->willReturn($mockQueryJobConfiguration);

        $mockBigQueryService->expects($this->once())
            ->method('runQuery')
            ->with($mockQueryJobConfiguration)
            ->willReturn($this->createMock(QueryResults::class));

        $queryParameterBag = new QueryParameterBag();
        $queryParameterBag->set(QueryParameter::UPLOAD, $this->createMock(Upload::class));

        $result = $queryService->runQuery($query, $queryParameterBag);

        $this->assertEquals($queryResult, $result);
    }

    public function testRunInvalidQuery(): void
    {
        $mockBigQueryService = $this->createMock(BigQueryClient::class);

        $mockQueryBuilder = $this->createMock(QueryBuilderService::class);

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
                    $this->createMock(TotalUploadsQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    LineCoverageQuery::class,
                    null,
                    $this->createMock(LineCoverageCollectionQueryResult::class)
                ),
            ],
            $mockQueryBuilder,
            new NullLogger()
        );

        $mockBigQueryService->expects($this->never())
            ->method('query');

        $mockBigQueryService->expects($this->never())
            ->method('runQuery');

        $mockQueryBuilder->expects($this->never())
            ->method('build');

        $this->expectException(QueryException::class);

        $queryService->runQuery('invalid-query');
    }

    public function testRunQueryWithExternalException(): void
    {
        $mockBigQueryService = $this->createMock(BigQueryClient::class);

        $mockQueryBuilderService = $this->createMock(QueryBuilderService::class);

        $queryService = new QueryService(
            $mockBigQueryService,
            [
                MockQueryFactory::createMock(
                    $this,
                    TotalCoverageQuery::class,
                    '',
                    $this->createMock(CoverageQueryResult::class)
                )
            ],
            $mockQueryBuilderService,
            new NullLogger()
        );

        $mockQueryJobConfiguration = $this->createMock(QueryJobConfiguration::class);

        $mockQueryBuilderService->expects($this->once())
            ->method('build')
            ->willReturn('formatted-query');

        $mockBigQueryService->expects($this->once())
            ->method('query')
            ->with('formatted-query')
            ->willReturn($mockQueryJobConfiguration);

        $mockBigQueryService->expects($this->once())
            ->method('runQuery')
            ->with($mockQueryJobConfiguration)
            ->willThrowException(new GoogleException());

        $queryParameterBag = new QueryParameterBag();
        $queryParameterBag->set(QueryParameter::UPLOAD, $this->createMock(Upload::class));

        $this->expectException(GoogleException::class);

        $queryService->runQuery(TotalCoverageQuery::class, $queryParameterBag);
    }

    public function testRunQueryWithQueryException(): void
    {
        $mockBigQueryService = $this->createMock(BigQueryClient::class);

        $mockQueryBuilderService = $this->createMock(QueryBuilderService::class);

        $queryService = new QueryService(
            $mockBigQueryService,
            [
                MockQueryFactory::createMock(
                    $this,
                    TotalCoverageQuery::class,
                    '',
                    $this->createMock(CoverageQueryResult::class)
                )
            ],
            $mockQueryBuilderService,
            new NullLogger()
        );

        $mockQueryJobConfiguration = $this->createMock(QueryJobConfiguration::class);

        $mockQueryBuilderService->expects($this->once())
            ->method('build')
            ->willReturn('formatted-query');

        $mockBigQueryService->expects($this->once())
            ->method('query')
            ->with('formatted-query')
            ->willReturn($mockQueryJobConfiguration);

        $mockBigQueryService->expects($this->once())
            ->method('runQuery')
            ->with($mockQueryJobConfiguration)
            ->willThrowException(new QueryException());

        $queryParameterBag = new QueryParameterBag();
        $queryParameterBag->set(QueryParameter::UPLOAD, $this->createMock(Upload::class));

        $this->expectException(QueryException::class);

        $queryService->runQuery(TotalCoverageQuery::class, $queryParameterBag);
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
                TotalUploadsQueryResult::from(["1", "2"], ["3"])
            ],
            'Diff coverage query' => [
                LineCoverageQuery::class,
                LineCoverageCollectionQueryResult::from([
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
