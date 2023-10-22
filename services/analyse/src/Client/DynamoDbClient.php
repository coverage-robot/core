<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Query\Result\QueryResultInterface;
use App\Service\EnvironmentService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\DynamoDb\Input\GetItemInput;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class DynamoDbClient
{
    /**
     * The default TTL for a query cache item, in seconds - currently 6 hours.
     */
    private const DEFAULT_QUERY_CACHE_TTL = 21600;

    public function __construct(
        private readonly \AsyncAws\DynamoDb\DynamoDbClient $dynamoDbClient,
        private readonly EnvironmentService $environmentService,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $dynamoDbClientLogger
    ) {
    }

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
        } catch (HttpException $exception) {
            $this->dynamoDbClientLogger->error(
                'Failed to retrieve query result in cache.',
                [
                    'cacheKey' => $cacheKey,
                    'exception' => $exception
                ]
            );

            return null;
        }

        $item = $response->getItem();

        if (isset($item['result'])) {
            return $this->serializer->deserialize(
                $item['result']->getS(),
                QueryResultInterface::class,
                'json'
            );
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

    public function putQueryResultInCache(string $cacheKey, QueryResultInterface $queryResult): bool
    {
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
                        'ttl' => [
                            'N' => (string)(time() + self::DEFAULT_QUERY_CACHE_TTL)
                        ],
                    ],
                ]
            );

            $response->resolve();
        } catch (HttpException $exception) {
            $this->dynamoDbClientLogger->error(
                'Failed to put query result in cache.',
                [
                    'cacheKey' => $cacheKey,
                    'exception' => $exception
                ]
            );

            return false;
        }

        return true;
    }
}
