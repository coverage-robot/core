<?php

namespace App\Service;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;

interface QueryServiceInterface
{
    /**
     * Run a query against a datastore (likely the data warehouse), using a set
     * of parameters to filter the results.
     *
     * @param class-string<QueryInterface> $queryClass
     *
     * @throws QueryException
     */
    public function runQuery(
        string $queryClass,
        ?QueryParameterBag $parameterBag = null
    ): QueryResultInterface;

    /**
     *  Get a fully instantiated query class from the query class string.
     *
     *  @param class-string<QueryInterface> $queryClass
     */
    public function getQueryClass(string $queryClass): QueryInterface;
}
