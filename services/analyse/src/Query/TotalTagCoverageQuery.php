<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\MultiTagCoverageQueryResult;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Enum\LineState;
use Packages\Models\Model\Upload;

class TotalTagCoverageQuery extends AbstractLineCoverageQuery
{
    public function getQuery(string $table, Upload $upload, ?QueryParameterBag $parameterBag = null): string
    {
        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        return <<<SQL
        {$this->getNamedQueries($table, $upload, $parameterBag)}
        SELECT
            tag,
            COUNT(*) as lines,
            SUM(IF(state = "{$covered}", 1, 0)) as covered,
            SUM(IF(state = "{$partial}", 1, 0)) as partial,
            SUM(IF(state = "{$uncovered}", 1, 0)) as uncovered,
            ROUND(
                (
                    SUM(IF(state = "{$covered}", 1, 0)) +
                    SUM(IF(state = "{$partial}", 1, 0))
                ) /
                COUNT(*)
                * 100,
                2
            ) as coveragePercentage
        FROM
            lineCoverage
        GROUP BY
            tag
        SQL;
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    public function parseResults(QueryResults $results): MultiTagCoverageQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        $rows = $results->rows();

        return MultiTagCoverageQueryResult::from($rows);
    }
}
