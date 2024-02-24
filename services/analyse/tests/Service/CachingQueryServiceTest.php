<?php

namespace App\Tests\Service;

use App\Client\DynamoDbClientInterface;
use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\CoverageQueryResult;
use App\Query\Result\QueryResultInterface;
use App\Query\Result\TotalCoverageQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\CachingQueryService;
use App\Service\QueryBuilderServiceInterface;
use App\Service\QueryServiceInterface;
use App\Tests\Mock\Factory\MockQueryFactory;
use Packages\Telemetry\Service\MetricServiceInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CachingQueryServiceTest extends KernelTestCase
{
    public function testRunUncacheableQuery(): void
    {
        $queryResult = new TotalUploadsQueryResult([], [], [], []);

        $parameters = (new QueryParameterBag())
            ->set(QueryParameter::COMMIT, 'mock-commit');

        $mockQueryService = $this->createMock(QueryServiceInterface::class);
        $mockQueryService->expects($this->once())
            ->method('getQueryClass')
            ->with(TotalUploadsQuery::class)
            ->willReturn(
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    QueryInterface::class,
                    '',
                    $queryResult,
                    false
                )
            );

        $mockQueryBuilderService = $this->createMock(QueryBuilderServiceInterface::class);
        $mockQueryBuilderService->expects($this->never())
            ->method('hash');

        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(TotalUploadsQuery::class, $parameters)
            ->willReturn($queryResult);

        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
        $mockDynamoDbClient->expects($this->never())
            ->method('tryFromQueryCache');
        $mockDynamoDbClient->expects($this->never())
            ->method('putQueryResultInCache');

        $cachingQueryService = new CachingQueryService(
            new NullLogger(),
            $mockQueryService,
            $mockQueryBuilderService,
            $mockDynamoDbClient,
            $this->createMock(MetricServiceInterface::class)
        );

        $cachingQueryService->runQuery(
            TotalUploadsQuery::class,
            $parameters
        );
    }

    public function testRunCacheableQueryWithoutExistingCachedValue(): void
    {
        $queryResult = new TotalCoverageQueryResult(
            100,
            1,
            1,
            0,
            0
        );

        $parameters = (new QueryParameterBag())
            ->set(QueryParameter::COMMIT, 'mock-commit');

        $mockQueryService = $this->createMock(QueryServiceInterface::class);
        $mockQueryService->expects($this->once())
            ->method('getQueryClass')
            ->with(TotalCoverageQuery::class)
            ->willReturn(
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    QueryInterface::class,
                    '',
                    $queryResult,
                    true
                )
            );

        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(TotalCoverageQuery::class, $parameters)
            ->willReturn($queryResult);

        $mockQueryBuilderService = $this->createMock(QueryBuilderServiceInterface::class);
        $mockQueryBuilderService->expects($this->once())
            ->method('hash')
            ->with(TotalCoverageQuery::class, $parameters)
            ->willReturn('mock-cache-key');

        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('tryFromQueryCache')
            ->willReturn(null);
        $mockDynamoDbClient->expects($this->once())
            ->method('putQueryResultInCache')
            ->willReturn(true);

        $cachingQueryService = new CachingQueryService(
            new NullLogger(),
            $mockQueryService,
            $mockQueryBuilderService,
            $mockDynamoDbClient,
            $this->createMock(MetricServiceInterface::class)
        );

        $cachingQueryService->runQuery(
            TotalCoverageQuery::class,
            $parameters
        );
    }

    public function testRunCacheableQueryWithCachedValue(): void
    {
        $parameters = (new QueryParameterBag())
            ->set(QueryParameter::COMMIT, 'mock-commit');

        $mockQueryService = $this->createMock(QueryServiceInterface::class);
        $mockQueryService->expects($this->once())
            ->method('getQueryClass')
            ->with(TotalCoverageQuery::class)
            ->willReturn(
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    QueryInterface::class,
                    '',
                    $this->createMock(QueryResultInterface::class),
                    true
                )
            );
        $mockQueryService->expects($this->never())
            ->method('runQuery');

        $mockQueryBuilderService = $this->createMock(QueryBuilderServiceInterface::class);
        $mockQueryBuilderService->expects($this->once())
            ->method('hash')
            ->with(TotalCoverageQuery::class, $parameters)
            ->willReturn('mock-cache-key');

        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('tryFromQueryCache')
            ->willReturn(new TotalCoverageQueryResult(100, 1, 1, 0, 0));
        $mockDynamoDbClient->expects($this->never())
            ->method('putQueryResultInCache');

        $cachingQueryService = new CachingQueryService(
            new NullLogger(),
            $mockQueryService,
            $mockQueryBuilderService,
            $mockDynamoDbClient,
            $this->createMock(MetricServiceInterface::class)
        );

        $cachingQueryService->runQuery(
            TotalCoverageQuery::class,
            $parameters
        );
    }
}
