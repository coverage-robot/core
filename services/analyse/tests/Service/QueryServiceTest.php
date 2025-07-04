<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Client\BigQueryClientInterface;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\LineCoverageQuery;
use App\Query\Result\QueryResultInterface;
use App\Query\Result\TotalCoverageQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\QueryBuilderService;
use App\Service\QueryBuilderServiceInterface;
use App\Service\QueryService;
use DateTimeImmutable;
use Google\Cloud\BigQuery\QueryJobConfiguration;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Core\Iterator\ItemIterator;
use Iterator;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Telemetry\Service\MetricServiceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class QueryServiceTest extends KernelTestCase
{
    #[DataProvider('queryDataProvider')]
    public function testRunQuery(
        string $query,
        array $bigQueryResponse,
        QueryResultInterface $queryResult
    ): void {
        $mockBigQueryService = $this->createMock(BigQueryClientInterface::class);

        $queryService = new QueryService(
            $mockBigQueryService,
            [
                $this->getContainer()
                    ->get(TotalCoverageQuery::class),
                $this->getContainer()
                    ->get(TotalUploadsQuery::class),
                $this->getContainer()
                    ->get(LineCoverageQuery::class),
            ],
            $this->getContainer()
                ->get(QueryBuilderService::class),
            new NullLogger(),
            $this->createMock(MetricServiceInterface::class)
        );

        $mockQueryJobConfiguration = $this->createMock(QueryJobConfiguration::class);
        $mockQueryJobConfiguration->expects($this->once())
            ->method('parameters')
            ->willReturn($mockQueryJobConfiguration);
        $mockQueryJobConfiguration->expects($this->once())
            ->method('setParamTypes')
            ->willReturn($mockQueryJobConfiguration);

        $mockItemIterator = $this->createMock(ItemIterator::class);
        $mockItemIterator->expects($this->once())
            ->method('current')
            ->willReturn($bigQueryResponse);

        $mockQueryResults = $this->createMock(QueryResults::class);
        $mockQueryResults->expects($this->once())
            ->method('rows')
            ->willReturn($mockItemIterator);

        $mockBigQueryService->expects($this->once())
            ->method('query')
            ->willReturn($mockQueryJobConfiguration);
        $mockBigQueryService->expects($this->once())
            ->method('runQuery')
            ->with($mockQueryJobConfiguration)
            ->willReturn($mockQueryResults);

        $result = $queryService->runQuery(
            $query,
            new QueryParameterBag()
                ->set(QueryParameter::REPOSITORY, 'mock-repository')
                ->set(QueryParameter::OWNER, 'mock-owner')
                ->set(QueryParameter::PROVIDER, Provider::GITHUB)
                ->set(QueryParameter::COMMIT, 'f49a3e9068189f392acc51885b839a028efa4677')
                ->set(
                    QueryParameter::UPLOADS,
                    ['0193ea3e-64b2-751e-ad50-817e4083c212', '0193ea3e-e1fe-78c0-88ed-d87af4a687fa']
                )
                ->set(QueryParameter::INGEST_PARTITIONS, [new DateTimeImmutable()])
        );

        $this->assertEquals($queryResult, $result);
    }

    public function testRunInvalidQuery(): void
    {
        $mockBigQueryService = $this->createMock(BigQueryClientInterface::class);

        $mockQueryBuilder = $this->createMock(QueryBuilderServiceInterface::class);

        $queryService = new QueryService(
            $mockBigQueryService,
            [
                $this->getContainer()
                    ->get(TotalCoverageQuery::class),
                $this->getContainer()
                    ->get(TotalUploadsQuery::class),
                $this->getContainer()
                    ->get(LineCoverageQuery::class),
            ],
            $mockQueryBuilder,
            new NullLogger(),
            $this->createMock(MetricServiceInterface::class)
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
        $mockBigQueryService = $this->createMock(BigQueryClientInterface::class);

        $mockQueryBuilderService = $this->createMock(QueryBuilderServiceInterface::class);

        $queryService = new QueryService(
            $mockBigQueryService,
            [
                $this->getContainer()
                    ->get(TotalCoverageQuery::class)
            ],
            $mockQueryBuilderService,
            new NullLogger(),
            $this->createMock(MetricServiceInterface::class)
        );

        $mockQueryJobConfiguration = $this->createMock(QueryJobConfiguration::class);

        $mockQueryBuilderService->expects($this->once())
            ->method('build')
            ->willReturn('formatted-query');

        $mockBigQueryService->expects($this->once())
            ->method('query')
            ->with('formatted-query')
            ->willReturn($mockQueryJobConfiguration);

        $mockQueryJobConfiguration->expects($this->once())
            ->method('parameters')
            ->with([])
            ->willReturn($mockQueryJobConfiguration);

        $mockQueryJobConfiguration->expects($this->once())
            ->method('setParamTypes')
            ->with([])
            ->willReturn($mockQueryJobConfiguration);

        $mockBigQueryService->expects($this->once())
            ->method('runQuery')
            ->with($mockQueryJobConfiguration)
            ->willThrowException(new GoogleException());

        $this->expectException(GoogleException::class);

        $queryService->runQuery(TotalCoverageQuery::class, new QueryParameterBag());
    }

    public function testRunQueryWithQueryException(): void
    {
        $mockBigQueryService = $this->createMock(BigQueryClientInterface::class);

        $mockQueryBuilderService = $this->createMock(QueryBuilderServiceInterface::class);

        $queryService = new QueryService(
            $mockBigQueryService,
            [
                $this->getContainer()
                    ->get(TotalCoverageQuery::class)
            ],
            $mockQueryBuilderService,
            new NullLogger(),
            $this->createMock(MetricServiceInterface::class)
        );

        $mockQueryJobConfiguration = $this->createMock(QueryJobConfiguration::class);

        $mockQueryBuilderService->expects($this->once())
            ->method('build')
            ->willReturn('formatted-query');

        $mockBigQueryService->expects($this->once())
            ->method('query')
            ->with('formatted-query')
            ->willReturn($mockQueryJobConfiguration);

        $mockQueryJobConfiguration->expects($this->once())
            ->method('parameters')
            ->with([])
            ->willReturn($mockQueryJobConfiguration);

        $mockQueryJobConfiguration->expects($this->once())
            ->method('setParamTypes')
            ->with([])
            ->willReturn($mockQueryJobConfiguration);

        $mockBigQueryService->expects($this->once())
            ->method('runQuery')
            ->with($mockQueryJobConfiguration)
            ->willThrowException(new QueryException());

        $this->expectException(QueryException::class);

        $queryService->runQuery(TotalCoverageQuery::class, new QueryParameterBag());
    }

    public static function queryDataProvider(): Iterator
    {
        yield 'Total coverage query' => [
            TotalCoverageQuery::class,
            [
                'coveragePercentage' => 0.0,
                'lines' => 6,
                'covered' => 1,
                'partial' => 2,
                'uncovered' => 3
            ],
            new TotalCoverageQueryResult(
                0,
                6,
                1,
                2,
                3
            )
        ];

        yield 'Total commit uploads query' => [
            TotalUploadsQuery::class,
            [
                'successfulUploads' => [
                        '0193ea3e-64b2-751e-ad50-817e4083c212',
                        '0193ea3e-e1fe-78c0-88ed-d87af4a687fa'
                    ],
                'successfulTags' => [
                    [
                        'name' => 'tag-1',
                        'commit' => 'f49a3e9068189f392acc51885b839a028efa4677',
                        'successfullyUploadedLines' => [1],
                    ],
                    [
                        'name' => 'tag-2',
                        'commit' => 'f49a3e9068189f392acc51885b839a028efa4677',
                        'successfullyUploadedLines' => [1],
                    ]
                ],
                'successfulIngestTimes' => [
                    '2021-01-01T00:00:00+0000',
                    '2021-01-01T00:00:00+0000'
                ]
            ],
            new TotalUploadsQueryResult(
                ['0193ea3e-64b2-751e-ad50-817e4083c212', '0193ea3e-e1fe-78c0-88ed-d87af4a687fa'],
                [new DateTimeImmutable('2021-01-01'), new DateTimeImmutable('2021-01-01')],
                [
                    new Tag('tag-1', 'f49a3e9068189f392acc51885b839a028efa4677', [1]),
                    new Tag('tag-2', 'f49a3e9068189f392acc51885b839a028efa4677', [1])
                ]
            )
        ];
    }
}
