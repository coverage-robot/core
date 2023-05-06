<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\Upload;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;

/**
 * @psalm-type CommitCoverage = array{
 *     coveragePercentage: float,
 *     lines: int,
 *     covered: int,
 *     partial: int,
 *     uncovered: int
 * }
 */
class TotalCommitCoverageQuery extends CommitLineCoverageQuery
{
    public function getQuery(string $table, Upload $upload): string
    {
        return <<<SQL
        {$this->getNamedSubqueries($table, $upload)}
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

    public function getNamedSubqueries(string $table, Upload $upload): string
    {
        $parent = parent::getNamedSubqueries($table, $upload);
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
    public function parseResults(QueryResults $results): array
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        $rows = $results->rows();

        /** @var array|null $coverageValues */
        $coverageValues = $rows->current() ?? null;

        if (
            is_float($coverageValues['coveragePercentage'] ?? null) &&
            is_int($coverageValues['lines'] ?? null) &&
            is_int($coverageValues['covered'] ?? null) &&
            is_int($coverageValues['partial'] ?? null) &&
            is_int($coverageValues['uncovered'] ?? null)
        ) {
            /** @var CommitCoverage $coverageValues */
            return $coverageValues;
        }

        throw QueryException::typeMismatch(gettype($coverageValues), 'array');
    }
}
