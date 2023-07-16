<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\MultiLineCoverageQueryResult;
use App\Query\Trait\CarryforwardAwareTrait;
use App\Query\Trait\DiffAwareTrait;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;

class LineCoverageQuery extends AbstractLineCoverageQuery
{
    use DiffAwareTrait;
    use CarryforwardAwareTrait;

    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        return <<<SQL
        {$this->getNamedQueries($table, $parameterBag)}
        SELECT
            *
        FROM
            lines
        SQL;
    }

    public function getUnnestQueryFiltering(?QueryParameterBag $parameterBag = null): string
    {
        $parent = parent::getUnnestQueryFiltering($parameterBag);
        $carryforwardScope = !empty($scope = self::getCarryforwardTagsScope($parameterBag)) ? 'OR ' . $scope : '' ;
        $lineScope = !empty($scope = self::getLineScope($parameterBag)) ? 'AND ' . $scope : '' ;

        return <<<SQL
        (
            {$parent}
            {$carryforwardScope}
        )
        {$lineScope}
        SQL;
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    public function parseResults(QueryResults $results): MultiLineCoverageQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        /** @var array $lines */
        $lines = $results->rows();

        return MultiLineCoverageQueryResult::from($lines);
    }
}
