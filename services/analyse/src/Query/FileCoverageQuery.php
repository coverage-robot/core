<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\MultiFileCoverageQueryResult;
use App\Query\Trait\ScopeAwareTrait;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Enum\LineState;

class FileCoverageQuery extends AbstractLineCoverageQuery
{
    use ScopeAwareTrait;

    public function getQuery(string $table, ?QueryParameterBag $parameterBag = null): string
    {
        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        $limit = self::getLimit($parameterBag);

        return <<<SQL
        {$this->getNamedQueries($table, $parameterBag)}
        SELECT
            fileName,
            COUNT(*) as lines,
            COALESCE(SUM(IF(state = "{$covered}", 1, 0)), 0) as covered,
            COALESCE(SUM(IF(state = "{$partial}", 1, 0)), 0) as partial,
            COALESCE(SUM(IF(state = "{$uncovered}", 1, 0)), 0) as uncovered,
            ROUND(
                (
                    SUM(IF(state = "{$covered}", 1, 0)) + SUM(IF(state = "{$partial}", 1, 0))
                ) /
                COUNT(*) * 100,
                2
            ) as coveragePercentage
        FROM
            lines
        GROUP BY
            fileName
        ORDER BY
            ROUND(
                (
                    SUM(IF(state = "{$covered}", 1, 0)) + SUM(IF(state = "{$partial}", 1, 0))
                ) /
                COUNT(*) * 100,
                2
            )
            ASC
        {$limit}
        SQL;
    }

    public function getUnnestQueryFiltering(?QueryParameterBag $parameterBag = null): string
    {
        $parent = parent::getUnnestQueryFiltering($parameterBag);
        $carryforward = self::getCarryforwardTagsScope($parameterBag);
        $lineScope = self::getLineScope($parameterBag);

        return <<<SQL
        ({$parent} {$carryforward})
        {$lineScope}
        SQL;
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    public function parseResults(QueryResults $results): MultiFileCoverageQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        /** @var array $files */
        $files = $results->rows();

        return MultiFileCoverageQueryResult::from($files);
    }
}
