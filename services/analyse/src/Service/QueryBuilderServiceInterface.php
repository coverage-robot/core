<?php

namespace App\Service;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;

interface QueryBuilderServiceInterface
{
    /**
     * Build a full query string, which can be executed on BigQuery, from a Query object.
     *
     * The query string will also be formatted for readability purposes.
     *
     * @throws QueryException
     */
    public function build(QueryInterface $query, string $table, ?QueryParameterBag $parameterBag = null): string;

    /**
     * Hash the contents of a query, using its parameters and the class name of the query.
     *
     * This function will also normalize the order of parameters in order to generate a
     * more predictable hash that is not affected by the order of the parameters.
     */
    public function hash(string $queryClass, ?QueryParameterBag $parameterBag): string;
}
