<?php

namespace App\Tests\Service;

use App\Client\DynamoDbClient;
use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use App\Query\Result\CoverageQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\CachingQueryService;
use App\Service\QueryBuilderService;
use App\Service\QueryService;
use App\Tests\Mock\Factory\MockQueryFactory;
use Packages\Telemetry\Service\MetricService;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CachingQueryServiceTest extends KernelTestCase
{
    public function testRunUncacheableQuery(): void
    {
        $queryResult = new TotalUploadsQueryResult([], [], [], null);

        $parameters = new QueryParameterBag();
        $parameters->set(QueryParameter::COMMIT, 'mock-commit');

        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->once())
            ->method('getQueryClass')
            ->with(TotalUploadsQuery::class)
            ->willReturn(
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    TotalUploadsQuery::class,
                    '',
                    $queryResult
                )
            );

        $mockQueryBuilderService = $this->createMock(QueryBuilderService::class);
        $mockQueryBuilderService->expects($this->never())
            ->method('hash');

        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(TotalUploadsQuery::class, $parameters)
            ->willReturn($queryResult);

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->never())
            ->method('tryFromQueryCache');
        $mockDynamoDbClient->expects($this->never())
            ->method('putQueryResultInCache');

        $cachingQueryService = new CachingQueryService(
            new NullLogger(),
            $mockQueryService,
            $mockQueryBuilderService,
            $mockDynamoDbClient,
            $this->createMock(MetricService::class)
        );

        $cachingQueryService->runQuery(
            TotalUploadsQuery::class,
            $parameters
        );
    }

    public function testRunCacheableQueryWithoutExistingCachedValue(): void
    {
        $queryResult = new CoverageQueryResult(
            100,
            1,
            1,
            0,
            0
        );

        $parameters = new QueryParameterBag();
        $parameters->set(QueryParameter::COMMIT, 'mock-commit');

        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->once())
            ->method('getQueryClass')
            ->with(TotalCoverageQuery::class)
            ->willReturn(
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    TotalCoverageQuery::class,
                    '',
                    $queryResult
                )
            );

        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(TotalCoverageQuery::class, $parameters)
            ->willReturn($queryResult);

        $mockQueryBuilderService = $this->createMock(QueryBuilderService::class);
        $mockQueryBuilderService->expects($this->once())
            ->method('hash')
            ->with(TotalCoverageQuery::class, $parameters)
            ->willReturn('mock-cache-key');

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
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
            $this->createMock(MetricService::class)
        );

        $cachingQueryService->runQuery(
            TotalCoverageQuery::class,
            $parameters
        );
    }

    public function testRunCacheableQueryWithCachedValue(): void
    {
        $parameters = new QueryParameterBag();
        $parameters->set(QueryParameter::COMMIT, 'mock-commit');

        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->once())
            ->method('getQueryClass')
            ->with(TotalCoverageQuery::class)
            ->willReturn(
                MockQueryFactory::createMock(
                    $this,
                    $this->getContainer(),
                    TotalCoverageQuery::class,
                    '',
                    $this->createMock(CoverageQueryResult::class)
                )
            );
        $mockQueryService->expects($this->never())
            ->method('runQuery');

        $mockQueryBuilderService = $this->createMock(QueryBuilderService::class);
        $mockQueryBuilderService->expects($this->once())
            ->method('hash')
            ->with(TotalCoverageQuery::class, $parameters)
            ->willReturn('mock-cache-key');

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('tryFromQueryCache')
            ->willReturn(new CoverageQueryResult(100, 1, 1, 0, 0));
        $mockDynamoDbClient->expects($this->never())
            ->method('putQueryResultInCache');

        $cachingQueryService = new CachingQueryService(
            new NullLogger(),
            $mockQueryService,
            $mockQueryBuilderService,
            $mockDynamoDbClient,
            $this->createMock(MetricService::class)
        );

        $cachingQueryService->runQuery(
            TotalCoverageQuery::class,
            $parameters
        );
    }
}
