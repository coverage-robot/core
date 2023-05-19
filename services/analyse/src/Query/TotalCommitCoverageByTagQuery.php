<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\Upload;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;

/**
 * @psalm-type CommitTagCoverage = array{
 *     coveragePercentage: float,
 *     tag: string,
 * }
 */
class TotalCommitCoverageByTagQuery implements QueryInterface
{
    public function getQuery(string $table, Upload $upload): string
    {
        return <<<SQL
        {$this->getNamedSubqueries($table, $upload)}
        SELECT
            tag,
            ROUND(
                (
                    SUM(IF(state = "covered", 1, 0)) + 
                    SUM(IF(state = "partial", 1, 0))
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

    public function getNamedSubqueries(string $table, Upload $upload): string
    {
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
                    "uncovered",
                    IF (
                        MAX(isPartiallyHit) = 1,
                        "partial",
                        "covered"
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
    public function parseResults(QueryResults $results): array
    {
        if (!$results->isComplete()) {
            throw new QueryException('Query was not complete when attempting to parse results.');
        }

        $tagCoverage = [];

        /** @var array[] $rows */
        $rows = $results->rows();

        foreach ($rows as $row) {
            if (
                is_float($row['coveragePercentage'] ?? null) &&
                is_string($row['tag'] ?? null)
            ) {
                /** @var CommitTagCoverage $row */
                $tagCoverage[] = $row;
            }
        }

        return $tagCoverage;
    }
}
