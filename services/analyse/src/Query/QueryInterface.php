<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\QueryResultInterface;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Model\Upload;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.coverage_query')]
interface QueryInterface
{
    /**
     * Get the names queries that are required to run the main query.
     */
    public function getNamedQueries(string $table, Upload $upload, ?QueryParameterBag $parameterBag = null): string;

    /**
     * Get the fully built query to run against BigQuery.
     */
    public function getQuery(string $table, Upload $upload, ?QueryParameterBag $parameterBag = null): string;

    /**
     * Parse the results of the query.
     *
     * @throws QueryException
     * @throws GoogleException
     */
    public function parseResults(QueryResults $results): QueryResultInterface;
}
