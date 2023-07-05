<?php

namespace App\Tests\Query;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use App\Query\LineCoverageQuery;
use App\Query\QueryInterface;

class LineCoverageQueryTest extends AbstractQueryTestCase
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
                    AND (    (
                    fileName LIKE "%mock-file" AND
                    lineNumber IN (1,2,3)
                ) OR    (
                    fileName LIKE "%mock-file-2" AND
                    lineNumber IN (10,11,12)
                ))
            ),
            branchingLines AS (
                SELECT
                    fileName,
                    lineNumber,
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
                    branchIndex
            ),
            lines AS (
                SELECT
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
                    fileName,
                    lineNumber
            )
            SELECT
                *
            FROM
                lines
            SQL,
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
                    branchIndex
            ),
            lines AS (
                SELECT
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
                    fileName,
                    lineNumber
            )
            SELECT
                *
            FROM
                lines
            SQL
        ];
    }

    public function getQueryClass(): QueryInterface
    {
        return new LineCoverageQuery();
    }

    public static function getQueryParameters(): array
    {
        $scopedParameters = new QueryParameterBag();
        $scopedParameters->set(
            QueryParameter::LINE_SCOPE,
            [
                'mock-file' => [1, 2, 3],
                'mock-file-2' => [10, 11, 12]
            ]
        );

        return [
            $scopedParameters,
            new QueryParameterBag()
        ];
    }
}
