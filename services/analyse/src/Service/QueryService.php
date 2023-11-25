<?php

namespace App\Service;

use App\Client\BigQueryClient;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;
use Google\Cloud\Core\Exception\GoogleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class QueryService implements QueryServiceInterface
{
    /**
     * @param QueryInterface[] $queries
     */
    public function __construct(
        private readonly BigQueryClient $bigQueryClient,
        #[TaggedIterator('app.coverage_query')]
        private readonly iterable $queries,
        private readonly QueryBuilderService $queryBuilderService,
        private readonly LoggerInterface $queryServiceLogger
    ) {
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
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
     * Get a fully instantiated query class from the query class string.
     *
     * @param class-string<QueryInterface> $queryClass
     * @throws QueryException
     */
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
            );

            $results->waitUntilComplete();

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
}
