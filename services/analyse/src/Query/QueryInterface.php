<?php

declare(strict_types=1);

namespace App\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\QueryResultInterface;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraint;

#[AutoconfigureTag('app.coverage_query')]
interface QueryInterface
{
    /**
     * Get the names queries that are required to run the main query.
     */
    public function getNamedQueries(?QueryParameterBag $parameterBag = null): string;

    /**
     * Get the fully built query to run against BigQuery.
     */
    public function getQuery(?QueryParameterBag $parameterBag = null): string;

    /**
     * Get the constraints in which the query parameters must adhere to in order for them
     * to be valid for the query.
     *
     * @return array<value-of<QueryParameter>, array<Constraint>>
     */
    public function getQueryParameterConstraints(): array;

    /**
     * Parse the results of the query.
     *
     * @throws QueryException
     * @throws GoogleException
     */
    public function parseResults(QueryResults $results): QueryResultInterface;
}
