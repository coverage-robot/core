<?php

declare(strict_types=1);

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Query\Result\QueryResultInterface;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\DynamoDb\Input\GetItemInput;
use InvalidArgumentException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class DynamoDbClient implements DynamoDbClientInterface
{
    public function __construct(
        private readonly \AsyncAws\DynamoDb\DynamoDbClient $dynamoDbClient,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $dynamoDbClientLogger
    ) {
    }

    #[Override]
    public function tryFromQueryCache(string $cacheKey): ?QueryResultInterface
    {
        try {
            $response = $this->dynamoDbClient->getItem(
                new GetItemInput(
                    [
                        'TableName' => $this->environmentService->getVariable(
                            EnvironmentVariable::QUERY_CACHE_TABLE_NAME
                        ),
                        'Key' => [
                            'cacheKey' => [
                                'S' => $cacheKey,
                            ],
                        ],
                    ]
                )
            );

            $response->resolve();
        } catch (HttpException $httpException) {
            $this->dynamoDbClientLogger->error(
                'Failed to retrieve query result in cache.',
                [
                    'cacheKey' => $cacheKey,
                    'exception' => $httpException
                ]
            );

            return null;
        }

        $item = $response->getItem();

        if (isset($item['result'])) {
            try {
                return $this->serializer->deserialize(
                    $item['result']->getS(),
                    QueryResultInterface::class,
                    'json'
                );
            } catch (ExceptionInterface $exception) {
                $this->dynamoDbClientLogger->error(
                    'Failed to deserialize query result from cache.',
                    [
                        'cacheKey' => $cacheKey,
                        'exception' => $exception
                    ]
                );
            }
        }

        $this->dynamoDbClientLogger->warning(
            'Response was either not set, or malformed in cache.',
            [
                'cacheKey' => $cacheKey,
                'item' => $item
            ]
        );

        return null;
    }

    #[Override]
    public function putQueryResultInCache(string $cacheKey, QueryResultInterface $queryResult): bool
    {
        $ttl = $queryResult->getTimeToLive();

        if ($ttl === false) {
            throw new InvalidArgumentException('Query result must have a TTL to be cached.');
        }

        try {
            $response = $this->dynamoDbClient->putItem(
                [
                    'TableName' => $this->environmentService->getVariable(EnvironmentVariable::QUERY_CACHE_TABLE_NAME),
                    'Item' => [
                        'cacheKey' => [
                            'S' => $cacheKey,
                        ],
                        'result' => [
                            'S' => $this->serializer->serialize($queryResult, 'json'),
                        ],
                        'expiry' => [
                            'N' => (string)(time() + $ttl)
                        ],
                    ],
                ]
            );

            $response->resolve();
        } catch (HttpException $httpException) {
            $this->dynamoDbClientLogger->error(
                'Failed to put query result in cache.',
                [
                    'cacheKey' => $cacheKey,
                    'exception' => $httpException
                ]
            );

            return false;
        }

        return true;
    }
}
