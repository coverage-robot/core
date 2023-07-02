<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\Result\MultiLineCoverageQueryResult;
use App\Query\Trait\ScopeAwareTrait;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Enum\LineState;
use Packages\Models\Model\Upload;

class LineCoverageQuery extends AbstractLineCoverageQuery
{
    use ScopeAwareTrait;

    public function getQuery(string $table, Upload $upload, ?QueryParameterBag $parameterBag = null): string
    {
        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        return <<<SQL
        {$this->getNamedQueries($table, $upload, $parameterBag)}
        SELECT
            fileName,
            lineNumber,
            IF(
                SUM(hits) = 0,
                "{$uncovered}",
                IF (
                    MIN(isPartiallyHit) = 1,
                    "{$partial}",
                    "{$covered}"
                )
            ) as state
        FROM
            lineCoverage
        GROUP BY
            fileName,
            lineNumber
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
