<?php

declare(strict_types=1);

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
     * @param class-string<QueryInterface> $class
     *
     * @throws QueryException
     */
    public function runQuery(
        string $class,
        ?QueryParameterBag $parameterBag = null
    ): QueryResultInterface;
}
