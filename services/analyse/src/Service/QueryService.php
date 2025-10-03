<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\Core\Exception\GoogleException;
use Override;
use Packages\Telemetry\Enum\Unit;
use Packages\Telemetry\Service\MetricServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class QueryService implements QueryServiceInterface
{
    /**
     * @param QueryInterface[] $queries
     */
    public function __construct(
        private readonly BigQueryClient $bigQueryClient,
        #[AutowireIterator('app.coverage_query')]
        private readonly iterable $queries,
        #[Autowire(service: QueryBuilderService::class)]
        private readonly QueryBuilderServiceInterface $queryBuilderService,
        private readonly LoggerInterface $queryServiceLogger,
        private readonly MetricServiceInterface $metricService
    ) {
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    #[Override]
    public function runQuery(
        string $class,
        ?QueryParameterBag $parameterBag = null
    ): QueryResultInterface {
        return $this->runQueryAndParseResult(
            $this->getQuery($class),
            $parameterBag
        );
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    private function runQueryAndParseResult(
        QueryInterface $query,
        ?QueryParameterBag $parameterBag = null
    ): QueryResultInterface {
        $sql = $this->queryBuilderService->build($query, $parameterBag);

        try {
            $results = $this->bigQueryClient->runQuery(
                $this->bigQueryClient->query($sql)
                    ->parameters($parameterBag?->toBigQueryParameters() ?? [])
                    ->setParamTypes($parameterBag?->toBigQueryParameterTypes() ?? [])
            );

            $results->waitUntilComplete();

            $this->metricService->put(
                metric: 'QueryBytesProcessed',
                value: (int)($results->info()['totalBytesProcessed'] ?? 0),
                unit: Unit::BYTES,
                dimensions: [
                    ['query']
                ],
                properties: [
                    'query' => $query::class
                ]
            );

            return $query->parseResults($results);
        } catch (QueryException $e) {
            $this->queryServiceLogger->critical(
                sprintf(
                    'Query %s failed to parse results.',
                    $query::class
                ),
                [
                    'exception' => $e,
                    'sql' => $sql,
                    'results' => $results ?? null,
                    'parameterBag' => $parameterBag
                ]
            );

            throw $e;
        } catch (GoogleException $e) {
            $this->queryServiceLogger->critical(
                sprintf(
                    'Query %s produced exception when executing.',
                    $query::class,
                ),
                [
                    'exception' => $e,
                    'sql' => $sql,
                    'parameterBag' => $parameterBag
                ]
            );

            throw $e;
        }
    }

    /**
     * Get a fully instantiated query class from the query class string.
     *
     * @param class-string<QueryInterface> $class
     *
     * @throws QueryException
     */
    private function getQuery(string $class): QueryInterface
    {
        foreach ($this->queries as $query) {
            if (
                $query instanceof $class &&
                is_subclass_of($query, QueryInterface::class)
            ) {
                return $query;
            }
        }

        throw new QueryException(sprintf('No query found with class name of %s.', $class));
    }
}
