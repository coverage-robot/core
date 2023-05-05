<?php

namespace App\Query;

use App\Exception\QueryException;
use App\Model\Upload;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Exception\GoogleException;

class CommitLineCoverageQuery implements QueryInterface
{
    public function getQuery(string $table, Upload $upload): string
    {
        return <<<SQL
        SELECT
            *
        FROM
            lineCoverage
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
        lineCoverage AS (
            SELECT
                fileName,
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
            throw new QueryException("Query was not complete when attempting to parse results.");
        }
        
        return $results->rows()->current();
    }
}
