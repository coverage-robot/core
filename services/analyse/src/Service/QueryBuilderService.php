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
     * Build a full query string, which can be executed on BigQuery, from a Query object.
     *
     * The query string will also be formatted for readability purposes.
     *
     * @throws QueryException
     */
    public function build(QueryInterface $query, string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $query->validateParameters($parameterBag);

        return $this->sqlFormatter->format(
            $query->getQuery(
                $table,
                $parameterBag
            )
        );
    }
}
