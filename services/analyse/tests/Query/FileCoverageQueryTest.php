<?php

namespace App\Tests\Query;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use App\Query\FileCoverageQuery;
use App\Query\QueryInterface;

class FileCoverageQueryTest extends AbstractQueryTestCase
{
    public function getQueryClass(): QueryInterface
    {
        return new FileCoverageQuery();
    }

    /**
     * @inheritDoc
     */
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
                    AND (    (
                    fileName LIKE "%mock-file" AND
                    lineNumber IN (1,2,3)
                ) OR    (
                    fileName LIKE "%mock-file-2" AND
                    lineNumber IN (10,11,12)
                ))
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
            )
            SELECT
                fileName,
                COUNT(*) as lines,
                COALESCE(SUM(IF(state = "covered", 1, 0)), 0) as covered,
                COALESCE(SUM(IF(state = "partial", 1, 0)), 0) as partial,
                COALESCE(SUM(IF(state = "uncovered", 1, 0)), 0) as uncovered,
                ROUND(
                    (
                        SUM(IF(state = "covered", 1, 0)) + SUM(IF(state = "partial", 1, 0))
                    ) /
                    COUNT(*) * 100,
                    2
                ) as coveragePercentage
            FROM
                lineCoverageWithState
            GROUP BY
                fileName
            ORDER BY
                ROUND(
                    (
                        SUM(IF(state = "covered", 1, 0)) + SUM(IF(state = "partial", 1, 0))
                    ) /
                    COUNT(*) * 100,
                    2
                )
                ASC

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
                    AND (    (
                    fileName LIKE "%mock-file" AND
                    lineNumber IN (1,2,3)
                ) OR    (
                    fileName LIKE "%mock-file-2" AND
                    lineNumber IN (10,11,12)
                ))
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
            )
            SELECT
                fileName,
                COUNT(*) as lines,
                COALESCE(SUM(IF(state = "covered", 1, 0)), 0) as covered,
                COALESCE(SUM(IF(state = "partial", 1, 0)), 0) as partial,
                COALESCE(SUM(IF(state = "uncovered", 1, 0)), 0) as uncovered,
                ROUND(
                    (
                        SUM(IF(state = "covered", 1, 0)) + SUM(IF(state = "partial", 1, 0))
                    ) /
                    COUNT(*) * 100,
                    2
                ) as coveragePercentage
            FROM
                lineCoverageWithState
            GROUP BY
                fileName
            ORDER BY
                ROUND(
                    (
                        SUM(IF(state = "covered", 1, 0)) + SUM(IF(state = "partial", 1, 0))
                    ) /
                    COUNT(*) * 100,
                    2
                )
                ASC
            LIMIT 50
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
            )
            SELECT
                fileName,
                COUNT(*) as lines,
                COALESCE(SUM(IF(state = "covered", 1, 0)), 0) as covered,
                COALESCE(SUM(IF(state = "partial", 1, 0)), 0) as partial,
                COALESCE(SUM(IF(state = "uncovered", 1, 0)), 0) as uncovered,
                ROUND(
                    (
                        SUM(IF(state = "covered", 1, 0)) + SUM(IF(state = "partial", 1, 0))
                    ) /
                    COUNT(*) * 100,
                    2
                ) as coveragePercentage
            FROM
                lineCoverageWithState
            GROUP BY
                fileName
            ORDER BY
                ROUND(
                    (
                        SUM(IF(state = "covered", 1, 0)) + SUM(IF(state = "partial", 1, 0))
                    ) /
                    COUNT(*) * 100,
                    2
                )
                ASC

            SQL
        ];
    }

    public static function getQueryParameters(): array
    {
        $lineScopedParameters = new QueryParameterBag();
        $lineScopedParameters->set(
            QueryParameter::LINE_SCOPE,
            [
                'mock-file' => [1, 2, 3],
                'mock-file-2' => [10, 11, 12]
            ]
        );

        $limitedParameters = new QueryParameterBag();
        $limitedParameters->set(
            QueryParameter::LIMIT,
            50
        );
        $limitedParameters->set(
            QueryParameter::LINE_SCOPE,
            [
                'mock-file' => [1, 2, 3],
                'mock-file-2' => [10, 11, 12]
            ]
        );

        return [
            $lineScopedParameters,
            $limitedParameters,
            new QueryParameterBag()
        ];
    }
}
