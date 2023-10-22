<?php

namespace App\Service;

use App\Client\DynamoDbClient;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class CachingQueryService implements QueryServiceInterface
{
    public function __construct(
        public readonly LoggerInterface $queryServiceLogger,
        public readonly QueryService $queryService,
        public readonly SerializerInterface $serializer,
        public readonly DynamoDbClient $dynamoDbClient,
    ) {
    }

    /**
     * Run a query against the data warehouse, with an added level of caching in front, which makes sure
     * to avoid hitting the warehouse for multiple identical queries.
     *
     * It uses the query class, and the parameters in order to generate a cache key, which is then used
     * as the lookup value against DynamoDB - the resulting item is then decoded back into a valid
     * query result.
     *
     * In the event of a cache miss, the query is passed through to the data warehouse, and the result is
     * cached for any subsequent queries.
     *
     * @param class-string<QueryInterface> $queryClass
     */
    public function runQuery(string $queryClass, ?QueryParameterBag $parameterBag = null): QueryResultInterface
    {
        $cacheKey = $this->generateQueryCacheKey($queryClass, $parameterBag);

        $result = $this->dynamoDbClient->tryFromQueryCache($cacheKey);

        if (!$result) {
            $this->queryServiceLogger->info(
                'Cache miss. Running query and caching result.',
                [
                    'queryClass' => $queryClass,
                    'parameterBag' => $parameterBag
                ]
            );

            $result = $this->queryService->runQuery($queryClass, $parameterBag);

            $this->dynamoDbClient->putQueryResultInCache($cacheKey, $result);

            return $result;
        }

        $this->queryServiceLogger->info(
            'Cache hit. Returning cached result.',
            [
                'queryClass' => $queryClass,
                'parameterBag' => $parameterBag
            ]
        );

        return $result;
    }

    /**
     * Generate a cache key for the query cache, using the parameter bag, and the query class.
     */
    private function generateQueryCacheKey(string $queryClass, ?QueryParameterBag $parameterBag): string
    {
        $parameters = [];

        if ($parameterBag) {
            /**
             * @psalm-suppress all
             */
            foreach ($parameterBag->getAll() as $key => $value) {
                $parameters[$key->name] = $value;
            }
        }

        return md5(
            implode(
                '',
                [
                    $queryClass,
                    $this->serializer->serialize($parameters, 'json')
                ]
            )
        );
    }
}
