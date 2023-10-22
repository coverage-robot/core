<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\QueryResultInterface;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.coverage_query')]
interface QueryInterface
{
    /**
     * Get the names queries that are required to run the main query.
     */
    public function getNamedQueries(string $table, ?QueryParameterBag $parameterBag = null): string;

    /**
     * Validate the parameters in the bag are sufficient to build the query in its entirety.
     *
     * @throws QueryException
     */
    public function validateParameters(?QueryParameterBag $parameterBag = null): void;

    /**
     * Get the fully built query to run against BigQuery.
     */
    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string;

    /**
     * The name of the table to run the query against.
     */
    public function getTable(): string;

    /**
     * Whether or not the query can be cached.
     */
    public function isCachable(): bool;

    /**
     * Parse the results of the query.
     *
     * @throws QueryException
     * @throws GoogleException
     */
    public function parseResults(QueryResults $results): QueryResultInterface;
}
