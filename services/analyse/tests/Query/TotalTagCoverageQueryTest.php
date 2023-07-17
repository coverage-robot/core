<?php

namespace App\Tests\Query;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\TotalTagCoverageQuery;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Tag;
use Packages\Models\Model\Upload;

class TotalTagCoverageQueryTest extends AbstractQueryTestCase
{
    public static function getExpectedQueries(): array
    {
        return [
            <<<SQL
            WITH
              unnested AS (
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
                      SUM(
                        CAST(branchHits AS INT64)
                      )
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
                      ) AS branchHits
                    WITH
                      OFFSET AS branchIndex
                    GROUP BY
                      branchIndex,
                      branchHits
                  ) as branchHits
                FROM
                  `mock-table`
                WHERE
                  (
                    commit = "mock-commit"
                    AND repository = "mock-repository"
                    AND owner = "mock-owner"
                    AND provider = "github"
                  )
              ),
              branchingLines AS (
                SELECT
                  fileName,
                  lineNumber,
                  tag,
                  commit,
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
                  ) AS branchHit
                WITH
                  OFFSET AS branchIndex
                GROUP BY
                  fileName,
                  lineNumber,
                  tag,
                  commit,
                  branchIndex
              ),
              lines AS (
                SELECT
                  tag,
                  commit,
                  fileName,
                  lineNumber,
                  IF(
                    SUM(hits) = 0,
                    "uncovered",
                    IF (
                      MIN(
                        CAST(isBranchedLineHit AS INT64)
                      ) = 0,
                      "partial",
                      "covered"
                    )
                  ) as state
                FROM
                  branchingLines
                GROUP BY
                  tag,
                  commit,
                  fileName,
                  lineNumber
              )
            SELECT
              tag,
              commit,
              COUNT(*) as lines,
              COALESCE(
                SUM(
                  IF(state = "covered", 1, 0)
                ),
                0
              ) as covered,
              COALESCE(
                SUM(
                  IF(state = "partial", 1, 0)
                ),
                0
              ) as partial,
              COALESCE(
                SUM(
                  IF(state = "uncovered", 1, 0)
                ),
                0
              ) as uncovered,
              ROUND(
                (
                  SUM(
                    IF(state = "covered", 1, 0)
                  ) + SUM(
                    IF(state = "partial", 1, 0)
                  )
                ) / COUNT(*) * 100,
                2
              ) as coveragePercentage
            FROM
              lines
            GROUP BY
              tag,
              commit
            SQL,
            <<<SQL
            WITH
              unnested AS (
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
                      SUM(
                        CAST(branchHits AS INT64)
                      )
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
                      ) AS branchHits
                    WITH
                      OFFSET AS branchIndex
                    GROUP BY
                      branchIndex,
                      branchHits
                  ) as branchHits
                FROM
                  `mock-table`
                WHERE
                  (
                    commit = "mock-commit"
                    AND repository = "mock-repository"
                    AND owner = "mock-owner"
                    AND provider = "github"
                  )
                  OR (
                    (
                      (
                        commit = "mock-commit"
                        AND tag = "1"
                      )
                      OR (
                        commit = "mock-commit"
                        AND tag = "2"
                      )
                      OR (
                        commit = "mock-commit-2"
                        AND tag = "3"
                      )
                      OR (
                        commit = "mock-commit-2"
                        AND tag = "4"
                      )
                    )
                    AND repository = "mock-repository"
                    AND owner = "mock-owner"
                    AND provider = "github"
                  )
              ),
              branchingLines AS (
                SELECT
                  fileName,
                  lineNumber,
                  tag,
                  commit,
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
                  ) AS branchHit
                WITH
                  OFFSET AS branchIndex
                GROUP BY
                  fileName,
                  lineNumber,
                  tag,
                  commit,
                  branchIndex
              ),
              lines AS (
                SELECT
                  tag,
                  commit,
                  fileName,
                  lineNumber,
                  IF(
                    SUM(hits) = 0,
                    "uncovered",
                    IF (
                      MIN(
                        CAST(isBranchedLineHit AS INT64)
                      ) = 0,
                      "partial",
                      "covered"
                    )
                  ) as state
                FROM
                  branchingLines
                GROUP BY
                  tag,
                  commit,
                  fileName,
                  lineNumber
              )
            SELECT
              tag,
              commit,
              COUNT(*) as lines,
              COALESCE(
                SUM(
                  IF(state = "covered", 1, 0)
                ),
                0
              ) as covered,
              COALESCE(
                SUM(
                  IF(state = "partial", 1, 0)
                ),
                0
              ) as partial,
              COALESCE(
                SUM(
                  IF(state = "uncovered", 1, 0)
                ),
                0
              ) as uncovered,
              ROUND(
                (
                  SUM(
                    IF(state = "covered", 1, 0)
                  ) + SUM(
                    IF(state = "partial", 1, 0)
                  )
                ) / COUNT(*) * 100,
                2
              ) as coveragePercentage
            FROM
              lines
            GROUP BY
              tag,
              commit
            SQL
        ];
    }

    public function getQueryClass(): QueryInterface
    {
        return new TotalTagCoverageQuery();
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

        $carryforwardParameters = QueryParameterBag::fromUpload($upload);
        $carryforwardParameters->set(QueryParameter::CARRYFORWARD_TAGS, [
            new Tag('1', 'mock-commit'),
            new Tag('2', 'mock-commit'),
            new Tag('3', 'mock-commit-2'),
            new Tag('4', 'mock-commit-2')
        ]);
        return [
            QueryParameterBag::fromUpload($upload),
            $carryforwardParameters
        ];
    }
}
