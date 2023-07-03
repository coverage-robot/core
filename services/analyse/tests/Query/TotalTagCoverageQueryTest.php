<?php

namespace App\Tests\Query;

use App\Query\QueryInterface;
use App\Query\TotalTagCoverageQuery;

class TotalTagCoverageQueryTest extends AbstractQueryTestCase
{
    public static function getExpectedQueries(): array
    {
        return [
            <<<SQL
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
                    `mock-table`
                WHERE
                    commit = 'mock-commit' AND
                    owner = 'mock-owner' AND
                    repository = 'mock-repository'
                    
            ),
            lineCoverage AS (
                SELECT
                    fileName,
                    lineNumber,
                    tag,
                    SUM(hits) as hits,
                    MIN(isPartiallyHit) as isPartiallyHit
                FROM
                    unnested
                GROUP BY
                    fileName,
                    lineNumber,
                    tag
            ),
            lineCoverageWithState AS (
                SELECT
                    *,
                    IF(
                        hits = 0,
                        "uncovered",
                        IF (
                            isPartiallyHit = 1,
                            "partial",
                            "covered"
                        )
                    ) as state
                FROM
                    lineCoverage
            )
            SELECT
                tag,
                COUNT(*) as lines,
                COALESCE(SUM(IF(state = "covered", 1, 0)), 0) as covered,
                COALESCE(SUM(IF(state = "partial", 1, 0)), 0) as partial,
                COALESCE(SUM(IF(state = "uncovered", 1, 0)), 0) as uncovered,
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
                lineCoverageWithState
            GROUP BY
                tag
            SQL
        ];
    }

    public function getQueryClass(): QueryInterface
    {
        return new TotalTagCoverageQuery();
    }
}
