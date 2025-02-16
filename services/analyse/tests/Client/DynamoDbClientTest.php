<?php

declare(strict_types=1);

namespace App\Tests\Client;

use App\Client\DynamoDbClient;
use App\Enum\EnvironmentVariable;
use App\Query\Result\QueryResultInterface;
use App\Query\Result\QueryResultIterator;
use App\Query\Result\TotalCoverageQueryResult;
use ArrayIterator;
use AsyncAws\Core\Response;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Result\GetItemOutput;
use AsyncAws\DynamoDb\Result\PutItemOutput;
use InvalidArgumentException;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\Service;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class DynamoDbClientTest extends KernelTestCase
{
    public function testPutQueryResultInCache(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getInfo')
            ->willReturn(\Symfony\Component\HttpFoundation\Response::HTTP_OK);

        $queryResult = new TotalCoverageQueryResult(1.2, 0, 0, 0, 0);

        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $mockClient->expects($this->once())
            ->method('putItem')
            ->with(
                self::callback(
                    function (array $parameters): bool {
                        $this->assertSame(
                            'mock-query-cache-table-name',
                            $parameters['TableName']
                        );
                        $this->assertSame('mock-cache-key', $parameters['Item']['cacheKey']['S']);
                        $this->assertSame(
                            json_encode(
                                [
                                    'coveragePercentage' => 1.2,
                                    'lines' => 0,
                                    'covered' => 0,
                                    'partial' => 0,
                                    'uncovered' => 0,
                                    'type' => 'COVERAGE'
                                ]
                            ),
                            $parameters['Item']['result']['S']
                        );
                        $this->assertIsNumeric($parameters['Item']['expiry']['N']);
                        return true;
                    }
                )
            )
            ->willReturn(
                new PutItemOutput(
                    new Response(
                        $mockResponse,
                        $this->createMock(HttpClientInterface::class),
                        new NullLogger()
                    )
                )
            );

        $dynamoDbClient = new DynamoDbClient(
            $mockClient,
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                Service::ANALYSE,
                [
                    EnvironmentVariable::QUERY_CACHE_TABLE_NAME->value => 'mock-query-cache-table-name'
                ]
            ),
            $this->getContainer()->get(SerializerInterface::class),
            new NullLogger()
        );

        $this->assertTrue(
            $dynamoDbClient->putQueryResultInCache(
                'mock-cache-key',
                $queryResult
            )
        );
    }

    public function testTryFromQueryCache(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'Item' => [
                    'result' => [
                        'S' => json_encode(
                            [
                                'coveragePercentage' => 1.1,
                                'lines' => 0,
                                'covered' => 0,
                                'partial' => 0,
                                'uncovered' => 0,
                                'type' => 'COVERAGE'
                            ]
                        )
                    ]
                ]
            ]);
        $mockResponse->method('getInfo')
            ->willReturn(\Symfony\Component\HttpFoundation\Response::HTTP_OK);

        $queryResult = new TotalCoverageQueryResult(
            1.1,
            0,
            0,
            0,
            0
        );

        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $mockClient->expects($this->once())
            ->method('getItem')
            ->with(
                self::callback(
                    function (GetItemInput $input): bool {
                        $this->assertSame(
                            'mock-query-cache-table-name',
                            $input->getTableName()
                        );
                        $this->assertSame('mock-cache-key', $input->getKey()['cacheKey']->getS());
                        return true;
                    }
                )
            )
            ->willReturn(
                new GetItemOutput(
                    new Response(
                        $mockResponse,
                        $this->createMock(HttpClientInterface::class),
                        new NullLogger()
                    )
                )
            );

        $dynamoDbClient = new DynamoDbClient(
            $mockClient,
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                Service::ANALYSE,
                [
                    EnvironmentVariable::QUERY_CACHE_TABLE_NAME->value => 'mock-query-cache-table-name'
                ]
            ),
            $this->getContainer()->get(SerializerInterface::class),
            new NullLogger()
        );

        $this->assertEquals(
            $queryResult,
            $dynamoDbClient->tryFromQueryCache(
                'mock-cache-key'
            )
        );
    }

    public function testTryIteratorFromQueryCache(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'Item' => [
                    'result' => [
                        'S' => json_encode([
                            [
                                'coveragePercentage' => 1.1,
                                'lines' => 0,
                                'covered' => 0,
                                'partial' => 0,
                                'uncovered' => 0,
                                'type' => 'COVERAGE'
                            ]
                        ])
                    ]
                ]
            ]);
        $mockResponse->method('getInfo')
            ->willReturn(\Symfony\Component\HttpFoundation\Response::HTTP_OK);

        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $mockClient->expects($this->once())
            ->method('getItem')
            ->with(
                self::callback(
                    function (GetItemInput $input): bool {
                        $this->assertSame(
                            'mock-query-cache-table-name',
                            $input->getTableName()
                        );
                        $this->assertSame('mock-cache-key', $input->getKey()['cacheKey']->getS());
                        return true;
                    }
                )
            )
            ->willReturn(
                new GetItemOutput(
                    new Response(
                        $mockResponse,
                        $this->createMock(HttpClientInterface::class),
                        new NullLogger()
                    )
                )
            );

        $dynamoDbClient = new DynamoDbClient(
            $mockClient,
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                Service::ANALYSE,
                [
                    EnvironmentVariable::QUERY_CACHE_TABLE_NAME->value => 'mock-query-cache-table-name'
                ]
            ),
            $this->getContainer()->get(SerializerInterface::class),
            new NullLogger()
        );

        $result = $dynamoDbClient->tryFromQueryCache('mock-cache-key');

        $this->assertInstanceOf(QueryResultIterator::class, $result);

        $this->assertCount(
            1,
            $result
        );
        $this->assertEquals(
            new TotalCoverageQueryResult(
                1.1,
                0,
                0,
                0,
                0
            ),
            $result->current()
        );
    }

    public function testPuttingQueryResultWithoutTimeToLiveInCache(): void
    {
        $queryResult = $this->createMock(QueryResultInterface::class);
        $queryResult->expects($this->once())
            ->method('getTimeToLive')
            ->willReturn(false);

        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $mockClient->expects($this->never())
            ->method('putItem');

        $dynamoDbClient = new DynamoDbClient(
            $mockClient,
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                Service::ANALYSE,
                [
                    EnvironmentVariable::QUERY_CACHE_TABLE_NAME->value => 'mock-query-cache-table-name'
                ]
            ),
            $this->getContainer()->get(SerializerInterface::class),
            new NullLogger()
        );

        $this->expectException(InvalidArgumentException::class);

        $dynamoDbClient->putQueryResultInCache(
            'mock-cache-key',
            $queryResult
        );
    }

    public function testPutQueryResultIteratorInCache(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getInfo')
            ->willReturn(\Symfony\Component\HttpFoundation\Response::HTTP_OK);

        $queryResult = new QueryResultIterator(
            new ArrayIterator([
                new TotalCoverageQueryResult(1.1, 0, 0, 0, 0)
            ]),
            1,
            static fn(QueryResultInterface $row): QueryResultInterface => $row
        );

        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $mockClient->expects($this->once())
            ->method('putItem')
            ->with(
                self::callback(
                    function (array $parameters): bool {
                        $this->assertSame(
                            'mock-query-cache-table-name',
                            $parameters['TableName']
                        );
                        $this->assertSame('mock-cache-key', $parameters['Item']['cacheKey']['S']);
                        $this->assertSame(
                            json_encode(
                                [
                                    [
                                        'coveragePercentage' => 1.1,
                                        'lines' => 0,
                                        'covered' => 0,
                                        'partial' => 0,
                                        'uncovered' => 0,
                                        'type' => 'COVERAGE'
                                    ]
                                ]
                            ),
                            $parameters['Item']['result']['S']
                        );
                        $this->assertIsNumeric($parameters['Item']['expiry']['N']);
                        return true;
                    }
                )
            )
            ->willReturn(
                new PutItemOutput(
                    new Response(
                        $mockResponse,
                        $this->createMock(HttpClientInterface::class),
                        new NullLogger()
                    )
                )
            );

        $dynamoDbClient = new DynamoDbClient(
            $mockClient,
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                Service::ANALYSE,
                [
                    EnvironmentVariable::QUERY_CACHE_TABLE_NAME->value => 'mock-query-cache-table-name'
                ]
            ),
            $this->getContainer()->get(SerializerInterface::class),
            new NullLogger()
        );

        $this->assertTrue(
            $dynamoDbClient->putQueryResultInCache(
                'mock-cache-key',
                $queryResult
            )
        );
    }
}
