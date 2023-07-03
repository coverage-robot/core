<?php

namespace App\Tests\Query;

use App\Query\QueryInterface;
use App\Query\TotalCoverageQuery;

class TotalCoverageQueryTest extends AbstractQueryTestCase
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
                    fileName,
                    lineNumber,
                    IF(
                        SUM(hits) = 0,
                        "uncovered",
                        IF (
                            MIN(isPartiallyHit) = 1,
                            "partial",
                            "covered"
                        )
                    ) as state
                FROM
                    lineCoverage
                GROUP BY
                    fileName,
                    lineNumber
            ),
            summedCoverage AS (
                SELECT
                    COUNT(*) as lines,
                    COALESCE(SUM(IF(state = "covered", 1, 0)), 0) as covered,
                    COALESCE(SUM(IF(state = "partial", 1, 0)), 0) as partial,
                    COALESCE(SUM(IF(state = "uncovered", 1, 0)), 0) as uncovered,
                FROM
                    lineCoverageWithState
            )
            SELECT
                SUM(lines) as lines,
                SUM(covered) as covered,
                SUM(partial) as partial,
                SUM(uncovered) as uncovered,
                ROUND((SUM(covered) + SUM(partial)) / IF(SUM(lines) = 0, 1, SUM(lines)) * 100, 2) as coveragePercentage
            FROM
                summedCoverage
            SQL
        ];
    }

    public function getQueryClass(): QueryInterface
    {
        return new TotalCoverageQuery();
    }
}
