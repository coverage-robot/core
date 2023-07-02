<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\CoverageQueryResult;
use App\Query\Trait\ScopeAwareTrait;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Enum\LineState;
use Packages\Models\Model\Upload;

class TotalCoverageQuery extends AbstractLineCoverageQuery
{
    use ScopeAwareTrait;

    public function getQuery(string $table, Upload $upload, ?QueryParameterBag $parameterBag = null): string
    {
        return <<<SQL
        {$this->getNamedQueries($table, $upload, $parameterBag)}
        SELECT
            SUM(lines) as lines,
            SUM(covered) as covered,
            SUM(partial) as partial,
            SUM(uncovered) as uncovered,
            ROUND((SUM(covered) + SUM(partial)) / IF(SUM(lines) = 0, 1, SUM(lines)) * 100, 2) as coveragePercentage
        FROM
            summedCoverage
        SQL;
    }

    public function getNamedQueries(string $table, Upload $upload, ?QueryParameterBag $parameterBag = null): string
    {
        $parent = parent::getNamedQueries($table, $upload, $parameterBag);

        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        return <<<SQL
        {$parent},
        summedCoverage AS (
            SELECT
                COUNT(*) as lines,
                COALESCE(SUM(IF(state = "{$covered}", 1, 0)), 0) as covered,
                COALESCE(SUM(IF(state = "{$partial}", 1, 0)), 0) as partial,
                COALESCE(SUM(IF(state = "{$uncovered}", 1, 0)), 0) as uncovered,
            FROM
                lineCoverage
        )
        SQL;
    }

    public function getUnnestQueryFiltering(?QueryParameterBag $parameterBag = null): string
    {
        return self::getLineScope($parameterBag);
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    public function parseResults(QueryResults $results): CoverageQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        /** @var array $coverageValues */
        $coverageValues = $results->rows()
            ->current();

        return CoverageQueryResult::from($coverageValues);
    }
}
