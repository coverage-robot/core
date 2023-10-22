<?php

namespace App\Service;

use App\Model\QueryParameterBag;
use App\Query\Result\QueryResultInterface;

interface QueryServiceInterface
{
    /**
     * Run a query against a datastore (likely the data warehouse), using a set
     * of parameters to filter the results.
     */
    public function runQuery(
        string $queryClass,
        ?QueryParameterBag $parameterBag = null
    ): QueryResultInterface;
}
