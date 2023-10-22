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
use App\Service\QueryService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use App\Tests\Mock\Factory\MockSerializerFactory;
use Monolog\Test\TestCase;
use Packages\Models\Enum\Environment;
use Psr\Log\NullLogger;

class CachingQueryServiceTest extends TestCase
{
    public function testRunUncacheableQuery(): void
    {
        $queryResult = TotalUploadsQueryResult::from('', [], []);

        $parameters = new QueryParameterBag();
        $parameters->set(QueryParameter::COMMIT, 'mock-commit');

        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->once())
            ->method('getQueryClass')
            ->with(TotalUploadsQuery::class)
            ->willReturn(
                new TotalUploadsQuery(
                    MockEnvironmentServiceFactory::getMock(
                        $this,
                        Environment::TESTING
                    )
                )
            );

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
            MockSerializerFactory::getMock(
                $this,
                serializeMap: [
                    [
                        [
                            QueryParameter::COMMIT->name => 'mock-commit'
                        ],
                        'json',
                        [],
                        'mock-serialized-parameters'
                    ]
                ]
            ),
            $mockDynamoDbClient
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
                new TotalCoverageQuery(
                    MockEnvironmentServiceFactory::getMock(
                        $this,
                        Environment::TESTING
                    )
                )
            );

        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(TotalCoverageQuery::class, $parameters)
            ->willReturn($queryResult);

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
            MockSerializerFactory::getMock(
                $this,
                serializeMap: [
                    [
                        [
                            QueryParameter::COMMIT->name => 'mock-commit'
                        ],
                        'json',
                        [],
                        'mock-serialized-parameters'
                    ]
                ]
            ),
            $mockDynamoDbClient
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
                new TotalCoverageQuery(
                    MockEnvironmentServiceFactory::getMock(
                        $this,
                        Environment::TESTING
                    )
                )
            );
        $mockQueryService->expects($this->never())
            ->method('runQuery');

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('tryFromQueryCache')
            ->willReturn(new CoverageQueryResult(100, 1, 1, 0, 0));
        $mockDynamoDbClient->expects($this->never())
            ->method('putQueryResultInCache');

        $cachingQueryService = new CachingQueryService(
            new NullLogger(),
            $mockQueryService,
            MockSerializerFactory::getMock(
                $this,
                serializeMap: [
                    [
                        [
                            QueryParameter::COMMIT->name => 'mock-commit'
                        ],
                        'json',
                        [],
                        'mock-serialized-parameters'
                    ]
                ]
            ),
            $mockDynamoDbClient
        );

        $cachingQueryService->runQuery(
            TotalCoverageQuery::class,
            $parameters
        );
    }
}
