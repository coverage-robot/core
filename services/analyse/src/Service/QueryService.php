<?php

namespace App\Service;

use App\Client\BigQueryClient;
use App\Client\BigQueryClientInterface;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Override;
use Packages\Telemetry\Enum\Unit;
use Packages\Telemetry\Service\MetricServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class QueryService implements QueryServiceInterface
{
    /**
     * @param QueryInterface[] $queries
     */
    public function __construct(
        #[Autowire(service: BigQueryClient::class)]
        private readonly BigQueryClientInterface $bigQueryClient,
        #[TaggedIterator('app.coverage_query')]
        private readonly iterable $queries,
        #[Autowire(service: QueryBuilderService::class)]
        private readonly QueryBuilderServiceInterface $queryBuilderService,
        private readonly ValidatorInterface $validator,
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
        string $queryClass,
        ?QueryParameterBag $parameterBag = null
    ): QueryResultInterface {
        return $this->runQueryAndParseResult(
            $this->getQueryClass($queryClass),
            $parameterBag
        );
    }

    /**
     * @inheritDoc
     *
     * @throws QueryException
     */
    #[Override]
    public function getQueryClass(string $queryClass): QueryInterface
    {
        foreach ($this->queries as $query) {
            if (
                $query instanceof $queryClass &&
                is_subclass_of($query, QueryInterface::class)
            ) {
                return $query;
            }
        }

        throw new QueryException(sprintf('No query found with class name of %s.', $queryClass));
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    private function runQueryAndParseResult(
        QueryInterface $query,
        ?QueryParameterBag $parameterBag = null
    ): QueryResultInterface {
        $sql = $this->queryBuilderService->build(
            $query,
            $this->bigQueryClient->getTable($query->getTable()),
            $parameterBag
        );

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

            return $this->parseAndValidateResults(
                $query,
                $results
            );
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
     * Parse the Query results from BigQuery into a model, and validate it.
     *
     * @throws QueryException
     */
    private function parseAndValidateResults(
        QueryInterface $query,
        QueryResults $results
    ): QueryResultInterface {
        $results = $query->parseResults($results);

        $errors = $this->validator->validate($results);

        if ($errors->count() > 0) {
            $this->queryServiceLogger->critical(
                sprintf(
                    'Query %s produced invalid results.',
                    $query::class
                ),
                [
                    'query' => $query,
                    'errors' => $errors,
                    'results' => $results
                ]
            );

            throw new QueryException(
                sprintf(
                    'Query results for %s was invalid.',
                    $query::class
                )
            );
        }

        return $results;
    }
}
