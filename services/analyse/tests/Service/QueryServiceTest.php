<?php

namespace App\Tests\Service;

use App\Client\BigQueryClient;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\LineCoverageQuery;
use App\Query\Result\CoverageQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageQueryResult;
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
use Packages\Contracts\Line\LineState;
use Packages\Contracts\Tag\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class QueryServiceTest extends KernelTestCase
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
                    $this->getContainer(),
                    TotalCoverageQuery::class,
                    '',
                    TotalCoverageQuery::class === $query ? $queryResult :
                        $this->createMock(CoverageQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    TotalUploadsQuery::class,
                    '',
                    TotalUploadsQuery::class === $query ? $queryResult :
                        $this->createMock(TotalUploadsQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    TotalTagCoverageQuery::class,
                    '',
                    TotalTagCoverageQuery::class === $query ? $queryResult :
                        $this->createMock(TagCoverageCollectionQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    LineCoverageQuery::class,
                    '',
                    LineCoverageQuery::class === $query ? $queryResult :
                        $this->createMock(LineCoverageCollectionQueryResult::class)
                )
            ],
            $mockQueryBuilderService,
            $this->createMock(ValidatorInterface::class),
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

        $result = $queryService->runQuery($query, new QueryParameterBag());

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
                    $this->getContainer(),
                    TotalCoverageQuery::class,
                    null,
                    $this->createMock(CoverageQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    TotalUploadsQuery::class,
                    null,
                    $this->createMock(TotalUploadsQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    LineCoverageQuery::class,
                    null,
                    $this->createMock(LineCoverageCollectionQueryResult::class)
                ),
            ],
            $mockQueryBuilder,
            $this->createMock(ValidatorInterface::class),
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

    #[DataProvider('queryDataProvider')]
    public function testRunQueryWithInvalidResults(
        string $query,
        QueryResultInterface $queryResult
    ): void {
        $mockBigQueryService = $this->createMock(BigQueryClient::class);

        $mockQueryBuilderService = $this->createMock(QueryBuilderService::class);

        $mockValidator = $this->createMock(ValidatorInterface::class);
        $mockValidator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList([
                new ConstraintViolation(
                    'mock-message',
                    'mock-template',
                    [],
                    'mock-root',
                    'mock-property-path',
                    'mock-invalid-value'
                )
            ]));

        $queryService = new QueryService(
            $mockBigQueryService,
            [
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    TotalCoverageQuery::class,
                    '',
                    TotalCoverageQuery::class === $query ? $queryResult :
                        $this->createMock(CoverageQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    TotalUploadsQuery::class,
                    '',
                    TotalUploadsQuery::class === $query ? $queryResult :
                        $this->createMock(TotalUploadsQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    TotalTagCoverageQuery::class,
                    '',
                    TotalTagCoverageQuery::class === $query ? $queryResult :
                        $this->createMock(TagCoverageCollectionQueryResult::class)
                ),
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    LineCoverageQuery::class,
                    '',
                    LineCoverageQuery::class === $query ? $queryResult :
                        $this->createMock(LineCoverageCollectionQueryResult::class)
                )
            ],
            $mockQueryBuilderService,
            $mockValidator,
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

        $this->expectException(QueryException::class);

        $result = $queryService->runQuery($query, new QueryParameterBag());
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
                    $this->getContainer(),
                    TotalCoverageQuery::class,
                    '',
                    $this->createMock(CoverageQueryResult::class)
                )
            ],
            $mockQueryBuilderService,
            $this->createMock(ValidatorInterface::class),
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

        $this->expectException(GoogleException::class);

        $queryService->runQuery(TotalCoverageQuery::class, new QueryParameterBag());
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
                    $this->getContainer(),
                    TotalCoverageQuery::class,
                    '',
                    $this->createMock(CoverageQueryResult::class)
                )
            ],
            $mockQueryBuilderService,
            $this->createMock(ValidatorInterface::class),
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

        $this->expectException(QueryException::class);

        $queryService->runQuery(TotalCoverageQuery::class, new QueryParameterBag());
    }

    public static function queryDataProvider(): array
    {
        return [
            'Total coverage query' => [
                TotalCoverageQuery::class,
                new CoverageQueryResult(
                    0,
                    6,
                    1,
                    2,
                    3
                )
            ],
            'Total commit uploads query' => [
                TotalUploadsQuery::class,
                new TotalUploadsQueryResult(
                    [1, 2],
                    [
                        new Tag('tag-1', 'mock-commit'),
                        new Tag('tag-2', 'mock-commit')
                    ],
                    null
                )
            ],
            'Diff coverage query' => [
                LineCoverageQuery::class,
                new LineCoverageCollectionQueryResult(
                    [
                        new LineCoverageQueryResult(
                            'test-file-1',
                            1,
                            LineState::UNCOVERED,
                            true,
                            false,
                            false,
                            0,
                            0
                        ),
                        new LineCoverageQueryResult(
                            'test-file-2',
                            2,
                            LineState::COVERED,
                            true,
                            false,
                            false,
                            0,
                            0
                        ),
                    ]
                )
            ],
        ];
    }
}
