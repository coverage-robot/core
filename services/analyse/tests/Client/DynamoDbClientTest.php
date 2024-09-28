<?php

namespace App\Tests\Client;

use App\Client\DynamoDbClient;
use App\Enum\EnvironmentVariable;
use App\Query\Result\QueryResultInterface;
use App\Query\Result\TotalUploadsQueryResult;
use Packages\Contracts\Environment\Service;
use App\Tests\Mock\Factory\MockSerializerFactory;
use AsyncAws\Core\Response;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Result\GetItemOutput;
use AsyncAws\DynamoDb\Result\PutItemOutput;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class DynamoDbClientTest extends TestCase
{
    public function testPutQueryResultInCache(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getInfo')
            ->willReturn(\Symfony\Component\HttpFoundation\Response::HTTP_OK);

        $queryResult = new TotalUploadsQueryResult([], [], []);

        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $mockClient->expects($this->once())
            ->method('putItem')
            ->with(
                self::callback(
                    function (array $parameters): bool {
                        $this->assertEquals(
                            'mock-query-cache-table-name',
                            $parameters['TableName']
                        );
                        $this->assertEquals($parameters['Item']['cacheKey']['S'], 'mock-cache-key');
                        $this->assertEquals($parameters['Item']['result']['S'], 'mock-serialized-result');
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
            MockSerializerFactory::getMock(
                $this,
                serializeMap: [
                    [
                        $queryResult,
                        'json',
                        [],
                        'mock-serialized-result'
                    ]
                ]
            ),
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
                        'S' => 'mock-serialized-result'
                    ]
                ]
            ]);
        $mockResponse->method('getInfo')
            ->willReturn(\Symfony\Component\HttpFoundation\Response::HTTP_OK);

        $queryResult = new TotalUploadsQueryResult([], [], []);

        $mockClient = $this->createMock(\AsyncAws\DynamoDb\DynamoDbClient::class);

        $mockClient->expects($this->once())
            ->method('getItem')
            ->with(
                self::callback(
                    function (GetItemInput $input): bool {
                        $this->assertEquals(
                            'mock-query-cache-table-name',
                            $input->getTableName()
                        );
                        $this->assertEquals($input->getKey()['cacheKey']->getS(), 'mock-cache-key');
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
            MockSerializerFactory::getMock(
                $this,
                deserializeMap: [
                    [
                        'mock-serialized-result',
                        QueryResultInterface::class,
                        'json',
                        [],
                        $queryResult
                    ]
                ]
            ),
            new NullLogger()
        );

        $this->assertEquals(
            $queryResult,
            $dynamoDbClient->tryFromQueryCache(
                'mock-cache-key'
            )
        );
    }
}
