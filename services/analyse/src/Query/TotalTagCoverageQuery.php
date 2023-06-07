<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\QueryResult\TotalTagCoverageQueryResult;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;
use Packages\Models\Enum\LineState;
use Packages\Models\Model\Upload;

class TotalTagCoverageQuery implements QueryInterface
{
    public function getQuery(string $table, Upload $upload): string
    {
        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        return <<<SQL
        {$this->getNamedQueries($table, $upload)}
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
            tagLineCoverage
        GROUP BY
            tag
        SQL;
    }

    public function getNamedQueries(string $table, Upload $upload): string
    {
        $covered = LineState::COVERED->value;
        $partial = LineState::PARTIAL->value;
        $uncovered = LineState::UNCOVERED->value;

        return <<<SQL
        WITH unnested AS (
            SELECT
                *,
                (
                    SELECT
                    IF (
                      value <> '',
                      CAST(value AS int),
                      0
                    )
                    FROM
                        UNNEST(metadata)
                    WHERE
                        key = "lineHits"
                ) AS hits,
                IF (
                    type = "BRANCH",
                    (
                        SELECT
                          IF (
                            value <> '',
                            CAST(value AS int),
                            0
                          )
                        FROM
                          UNNEST(metadata)
                        WHERE
                          KEY = "partial"
                    ),
                    0
                ) AS isPartiallyHit
            FROM
                `$table`
            WHERE
                commit = '{$upload->getCommit()}' AND
                owner = '{$upload->getOwner()}' AND
                repository = '{$upload->getRepository()}'
        ),
        tagLineCoverage AS (
            SELECT
                fileName,
                tag,
                lineNumber,
                IF(
                    SUM(hits) = 0,
                    "{$uncovered}",
                    IF (
                        MAX(isPartiallyHit) = 1,
                        "{$partial}",
                        "{$covered}"
                    )
                ) as state
            FROM
                unnested
            GROUP BY
                fileName,
                tag,
                lineNumber
        )
        SQL;
    }

    /**
     * @throws GoogleException
     * @throws QueryException
     */
    public function parseResults(QueryResults $results): TotalTagCoverageQueryResult
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        $rows = $results->rows();

        return TotalTagCoverageQueryResult::from($rows);
    }
}
