<?php

namespace App\Service;

use App\Client\DynamoDbClient;
use App\Client\DynamoDbClientInterface;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;
use Google\Cloud\Core\Exception\GoogleException;
use Override;
use Packages\Telemetry\Enum\Unit;
use Packages\Telemetry\Service\MetricService;
use Packages\Telemetry\Service\MetricServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CachingQueryService implements QueryServiceInterface
{
    public function __construct(
        public readonly LoggerInterface $queryServiceLogger,
        #[Autowire(service: QueryService::class)]
        public readonly QueryServiceInterface $queryService,
        #[Autowire(service: QueryBuilderService::class)]
        public readonly QueryBuilderServiceInterface $queryBuilderService,
        #[Autowire(service: DynamoDbClient::class)]
        public readonly DynamoDbClientInterface $dynamoDbClient,
        private readonly MetricServiceInterface $metricService
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
    #[Override]
    public function runQuery(string $queryClass, ?QueryParameterBag $parameterBag = null): QueryResultInterface
    {
        if (!$this->queryService->getQueryClass($queryClass)->isCachable()) {
            // The query isn't cacheable, so just act as a direct pass-through,
            // and run the query against the data warehouse.
            $this->queryServiceLogger->info(
                'Query is not cachable. Running query and not caching result.',
                [
                    'queryClass' => $queryClass,
                    'parameterBag' => $parameterBag
                ]
            );

            return $this->runUncachedQuery($queryClass, $parameterBag);
        }

        $cacheKey = $this->queryBuilderService->hash($queryClass, $parameterBag);

        $result = $this->dynamoDbClient->tryFromQueryCache($cacheKey);

        if (!$result instanceof QueryResultInterface) {
            $this->queryServiceLogger->info(
                'Cache miss. Running query and caching result.',
                [
                    'cacheKey' => $cacheKey,
                    'queryClass' => $queryClass,
                    'parameterBag' => $parameterBag
                ]
            );

            $result = $this->runUncachedQuery($queryClass, $parameterBag);

            $this->metricService->put(
                metric: 'QueryCacheMiss',
                value: 1,
                unit: Unit::COUNT,
                dimensions: [
                    ['query']
                ],
                properties: [
                    'query' => $queryClass
                ]
            );

            $this->dynamoDbClient->putQueryResultInCache($cacheKey, $result);

            return $result;
        }

        $this->queryServiceLogger->info(
            'Cache hit. Returning cached result.',
            [
                'cacheKey' => $cacheKey,
                'result' => $result,
                'queryClass' => $queryClass,
                'parameterBag' => $parameterBag
            ]
        );

        $this->metricService->put(
            metric: 'QueryCacheHit',
            value: 1,
            unit: Unit::COUNT,
            dimensions: [
                ['query']
            ],
            properties: [
                'query' => $queryClass
            ]
        );

        return $result;
    }

    /**
     * Run a query against the data warehouse, without any caching.
     *
     * @param class-string<QueryInterface> $queryClass
     *
     * @throws QueryException
     * @throws GoogleException
     */
    private function runUncachedQuery(
        string $queryClass,
        ?QueryParameterBag $parameterBag
    ): QueryResultInterface {
        return $this->queryService->runQuery($queryClass, $parameterBag);
    }

    #[\Override]
    public function getQueryClass(string $queryClass): QueryInterface
    {
        return $this->queryService->getQueryClass($queryClass);
    }
}
