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
                    ARRAY(
                        SELECT
                            SUM(CAST(branchHits AS INT64))
                        FROM
                            UNNEST(
                                JSON_VALUE_ARRAY(
                                    (
                                        SELECT
                                            value
                                        FROM
                                            UNNEST(metadata)
                                        WHERE
                                            KEY = "branchHits"
                                    )
                                ) 
                            ) AS branchHits WITH OFFSET AS branchIndex
                        GROUP BY
                            branchIndex,
                            branchHits
                    ) as branchHits
                FROM
                    `mock-table`
                WHERE
                    commit = 'mock-commit' AND
                    owner = 'mock-owner' AND
                    repository = 'mock-repository'
                    
            ),
            branchingLines AS (
                SELECT
                    fileName,
                    lineNumber,
                    tag,
                    SUM(hits) as hits,
                    branchIndex,
                    SUM(branchHit) > 0 as isBranchedLineHit
                FROM 
                    unnested,
                    UNNEST(
                        IF(
                            ARRAY_LENGTH(branchHits) = 0,
                            [hits],
                            branchHits    
                        )
                    ) AS branchHit WITH OFFSET AS branchIndex
                GROUP BY 
                    fileName,
                    lineNumber,
                    tag,
                    branchIndex
            ),
            lines AS (
                SELECT
                    tag,
                    fileName,
                    lineNumber,
                    IF(
                        SUM(hits) = 0,
                        "uncovered",
                        IF (
                            MIN(CAST(isBranchedLineHit AS INT64)) = 0,
                            "partial",
                            "covered"
                        )
                    ) as state
                FROM
                    branchingLines
                GROUP BY
                    tag,
                    fileName,
                    lineNumber
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
                lines
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
