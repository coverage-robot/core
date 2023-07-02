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
            )
            SELECT
                *,
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
            )
            SELECT
                *,
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
