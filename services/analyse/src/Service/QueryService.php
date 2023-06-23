<?php

namespace App\Service;

use App\Client\BigQueryClient;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;
use Packages\Models\Model\Upload;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class QueryService
{
    /**
     * @param BigQueryClient $bigQueryClient
     * @param QueryInterface[] $queries
     */
    public function __construct(
        private readonly BigQueryClient $bigQueryClient,
        #[TaggedIterator('app.coverage_query')]
        private readonly iterable $queries
    ) {
    }

    /**
     * @param class-string $queryClass
     * @param Upload $upload
     * @return QueryResultInterface
     *
     * @throws QueryException
     */
    public function runQuery(
        string $queryClass,
        Upload $upload,
        ?QueryParameterBag $parameterBag = null
    ): QueryResultInterface {
        foreach ($this->queries as $query) {
            if (
                $query instanceof $queryClass &&
                is_subclass_of($query, QueryInterface::class)
            ) {
                return $this->runQueryAndParseResult($query, $upload, $parameterBag);
            }
        }

        throw new QueryException(sprintf('No query found with class name of %s.', $queryClass));
    }

    private function runQueryAndParseResult(
        QueryInterface $query,
        Upload $upload,
        ?QueryParameterBag $parameterBag = null
    ): QueryResultInterface {
        $sql = $query->getQuery(
            $this->bigQueryClient->getTable(),
            $upload,
            $parameterBag
        );

        // Normalise the SQL to remove any whitespace and empty lines
        $sql = preg_replace('/^\h*\v+/m', '', trim($sql));

        $results = $this->bigQueryClient->runQuery(
            $this->bigQueryClient->query($sql)
        );

        $results->waitUntilComplete();

        return $query->parseResults($results);
    }
}
