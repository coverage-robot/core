<?php

namespace App\Tests\Query;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use App\Query\FileCoverageQuery;
use App\Query\QueryInterface;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;

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
                lines
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
                lines
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
                lines
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
        $upload = Upload::from([
            'provider' => Provider::GITHUB->value,
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'commit' => 'mock-commit',
            'uploadId' => 'mock-uploadId',
            'ref' => 'mock-ref',
            'parent' => [],
            'tag' => 'mock-tag',
        ]);

        $lineScopedParameters = new QueryParameterBag();
        $lineScopedParameters->set(
            QueryParameter::LINE_SCOPE,
            [
                'mock-file' => [1, 2, 3],
                'mock-file-2' => [10, 11, 12]
            ]
        );
        $lineScopedParameters->set(QueryParameter::UPLOAD, $upload);

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
        $limitedParameters->set(QueryParameter::UPLOAD, $upload);

        return [
            $lineScopedParameters,
            $limitedParameters,
            QueryParameterBag::fromUpload($upload)
        ];
    }
}
