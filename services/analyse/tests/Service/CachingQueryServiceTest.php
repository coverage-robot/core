<?php

namespace App\Tests\Service;

use App\Client\DynamoDbClient;
use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalUploadsQuery;
use App\Service\CachingQueryService;
use App\Service\QueryService;
use App\Tests\Mock\Factory\MockSerializerFactory;
use Monolog\Test\TestCase;
use Psr\Log\NullLogger;

class CachingQueryServiceTest extends TestCase
{
    public function testRunQueryWithoutExistingCachedValue(): void
    {
        $queryResult = TotalUploadsQueryResult::from('', [], []);

        $parameters = new QueryParameterBag();
        $parameters->set(QueryParameter::COMMIT, 'mock-commit');

        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(TotalUploadsQuery::class, $parameters)
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
            TotalUploadsQuery::class,
            $parameters
        );
    }

    public function testRunQueryWithCachedValue(): void
    {
        $parameters = new QueryParameterBag();
        $parameters->set(QueryParameter::COMMIT, 'mock-commit');

        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->never())
            ->method('runQuery');

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('tryFromQueryCache')
            ->willReturn(TotalUploadsQueryResult::from('', [], []));
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
}
