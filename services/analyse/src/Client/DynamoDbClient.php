<?php

declare(strict_types=1);

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Query\Result\QueryResultInterface;
use App\Query\Result\QueryResultIterator;
use ArrayIterator;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\DynamoDb\Input\GetItemInput;
use InvalidArgumentException;
use JsonException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class DynamoDbClient implements DynamoDbClientInterface
{
    public function __construct(
        private readonly \AsyncAws\DynamoDb\DynamoDbClient $dynamoDbClient,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly SerializerInterface&DenormalizerInterface $serializer,
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

        if (
            isset($item['result']) &&
            is_string($item['result']->getS())
        ) {
            try {
                $result = json_decode(
                    $item['result']->getS(),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if (
                    !is_array($result) ||
                    !array_is_list($result)
                ) {
                    return $this->serializer->denormalize(
                        $result,
                        QueryResultInterface::class,
                        'json'
                    );
                }

                /**
                 * We've got a list of results, meaning this _must have been_ an iterator before it was
                 * serialized into cache.
                 *
                 * Given we've already done the cheap(er) JSON decode, we can now denormalize each row
                 * into the correct result type on-the-fly, before returning each result from the new iterator.
                 */
                return new QueryResultIterator(
                    new ArrayIterator($result),
                    count($result),
                    fn(mixed $row): QueryResultInterface => $this->serializer->denormalize(
                        $row,
                        QueryResultInterface::class,
                        'json'
                    )
                );
            } catch (ExceptionInterface $exception) {
                $this->dynamoDbClientLogger->error(
                    'Failed to denormalize query result from cache.',
                    [
                        'cacheKey' => $cacheKey,
                        'exception' => $exception
                    ]
                );
            } catch (JsonException $e) {
                $this->dynamoDbClientLogger->error(
                    'Failed to decode query result from cache.',
                    [
                        'cacheKey' => $cacheKey,
                        'exception' => $e
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
