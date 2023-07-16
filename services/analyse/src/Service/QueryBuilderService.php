<?php

namespace App\Service;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use Doctrine\SqlFormatter\SqlFormatter;

class QueryBuilderService
{
    public function __construct(
        private readonly SqlFormatter $sqlFormatter
    ) {
    }

    /**
     * @throws QueryException
     */
    public function build(QueryInterface $query, string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $query->validateParameters($parameterBag);

        $sql = $query->getQuery(
            $table,
            $parameterBag
        );

        return $this->sqlFormatter->format($sql);
    }
}
