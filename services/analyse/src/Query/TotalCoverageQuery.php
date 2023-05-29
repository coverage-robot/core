<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryResult\TotalCoverageQueryResult;
use App\Model\Upload;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;

class TotalCoverageQuery extends AbstractCoverageQuery
{
    public function getQuery(string $table, Upload $upload): string
    {
        return <<<SQL
        {$this->getNamedQueries($table, $upload)}
        SELECT
            SUM(lines) as lines,
            SUM(covered) as covered,
            SUM(partial) as partial,
            SUM(uncovered) as uncovered,
            ROUND((SUM(covered) + SUM(partial)) / SUM(lines) * 100, 2) as coveragePercentage
        FROM
            summedCoverage
        SQL;
    }

    public function getNamedQueries(string $table, Upload $upload): string
    {
        $parent = parent::getNamedQueries($table, $upload);
        return <<<SQL
        {$parent},
        summedCoverage AS (
            SELECT
                COUNT(*) as lines,
                SUM(IF(state = "covered", 1, 0)) as covered,
                SUM(IF(state = "partial", 1, 0)) as partial,
                SUM(IF(state = "uncovered", 1, 0)) as uncovered,
            FROM
                lineCoverage
        )
        SQL;
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    public function parseResults(QueryResults $results): TotalCoverageQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        /** @var array $coverageValues */
        $coverageValues = $results->rows()
            ->current();

        return TotalCoverageQueryResult::from($coverageValues);
    }
}
