<?php

namespace App\Tests\Query;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\LineCoverageQuery;
use App\Query\QueryInterface;
use App\Query\Result\LineCoverageCollectionQueryResult;
use Google\Cloud\BigQuery\QueryResults;
use Packages\Models\Enum\LineState;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Tag;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;

class LineCoverageQueryTest extends AbstractQueryTestCase
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
                    (
                      commit = "mock-commit"
                      AND repository = "mock-repository"
                      AND owner = "mock-owner"
                      AND provider = "github"
                      AND totalLines >= (
                        SELECT
                          COUNT(uploadId)
                        FROM
                          `mock-table`
                        WHERE
                          uploadId = "mock-uploadId"
                          AND repository = "mock-repository"
                          AND owner = "mock-owner"
                          AND provider = "github"
                        GROUP BY
                          uploadId
                      )
                    )
                  )
                  AND (
                    (
                      fileName = "mock-file"
                      AND lineNumber IN (1, 2, 3)
                    )
                    OR(
                      fileName = "mock-file-2"
                      AND lineNumber IN (10, 11, 12)
                    )
                  )
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
                  ) AS branchHit
                WITH
                  OFFSET AS branchIndex
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
                  fileName,
                  lineNumber
              )
            SELECT
              *
            FROM
              lines
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
                    (
                      commit = "mock-commit"
                      AND repository = "mock-repository"
                      AND owner = "mock-owner"
                      AND provider = "github"
                      AND totalLines >= (
                        SELECT
                          COUNT(uploadId)
                        FROM
                          `mock-table`
                        WHERE
                          uploadId = "mock-uploadId"
                          AND repository = "mock-repository"
                          AND owner = "mock-owner"
                          AND provider = "github"
                        GROUP BY
                          uploadId
                      )
                    )
                  )
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
                  ) AS branchHit
                WITH
                  OFFSET AS branchIndex
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
                  fileName,
                  lineNumber
              )
            SELECT
              *
            FROM
              lines
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
                    (
                      commit = "mock-commit"
                      AND repository = "mock-repository"
                      AND owner = "mock-owner"
                      AND provider = "github"
                      AND totalLines >= (
                        SELECT
                          COUNT(uploadId)
                        FROM
                          `mock-table`
                        WHERE
                          uploadId = "mock-uploadId"
                          AND repository = "mock-repository"
                          AND owner = "mock-owner"
                          AND provider = "github"
                        GROUP BY
                          uploadId
                      )
                    )
                    OR (
                      (
                        uploadId IN (
                          SELECT
                            DISTINCT (
                              IF (
                                COUNT(uploadId) >= totalLines,
                                uploadId,
                                NULL
                              )
                            )
                          FROM
                            `mock-table`
                          WHERE
                            commit = "mock-commit"
                            AND tag = "1"
                            AND repository = "mock-repository"
                            AND owner = "mock-owner"
                            AND provider = "github"
                          GROUP BY
                            uploadId,
                            totalLines
                        )
                        OR uploadId IN (
                          SELECT
                            DISTINCT (
                              IF (
                                COUNT(uploadId) >= totalLines,
                                uploadId,
                                NULL
                              )
                            )
                          FROM
                            `mock-table`
                          WHERE
                            commit = "mock-commit"
                            AND tag = "2"
                            AND repository = "mock-repository"
                            AND owner = "mock-owner"
                            AND provider = "github"
                          GROUP BY
                            uploadId,
                            totalLines
                        )
                        OR uploadId IN (
                          SELECT
                            DISTINCT (
                              IF (
                                COUNT(uploadId) >= totalLines,
                                uploadId,
                                NULL
                              )
                            )
                          FROM
                            `mock-table`
                          WHERE
                            commit = "mock-commit-2"
                            AND tag = "3"
                            AND repository = "mock-repository"
                            AND owner = "mock-owner"
                            AND provider = "github"
                          GROUP BY
                            uploadId,
                            totalLines
                        )
                        OR uploadId IN (
                          SELECT
                            DISTINCT (
                              IF (
                                COUNT(uploadId) >= totalLines,
                                uploadId,
                                NULL
                              )
                            )
                          FROM
                            `mock-table`
                          WHERE
                            commit = "mock-commit-2"
                            AND tag = "4"
                            AND repository = "mock-repository"
                            AND owner = "mock-owner"
                            AND provider = "github"
                          GROUP BY
                            uploadId,
                            totalLines
                        )
                      )
                      AND repository = "mock-repository"
                      AND owner = "mock-owner"
                      AND provider = "github"
                    )
                  )
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
                  ) AS branchHit
                WITH
                  OFFSET AS branchIndex
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
        $upload = Upload::from([
            'provider' => Provider::GITHUB->value,
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'commit' => 'mock-commit',
            'uploadId' => 'mock-uploadId',
            'ref' => 'mock-ref',
            'parent' => [],
            'tag' => 'mock-tag',
            'ingestTime' => '2021-01-01T00:00:00+00:00'
        ]);

        $scopedParameters = QueryParameterBag::fromUpload($upload);
        $scopedParameters->set(
            QueryParameter::LINE_SCOPE,
            [
                'mock-file' => [1, 2, 3],
                'mock-file-2' => [10, 11, 12]
            ]
        );

        $carryforwardParameters = QueryParameterBag::fromUpload($upload);
        $carryforwardParameters->set(
            QueryParameter::CARRYFORWARD_TAGS,
            [
                new Tag('1', 'mock-commit'),
                new Tag('2', 'mock-commit'),
                new Tag('3', 'mock-commit-2'),
                new Tag('4', 'mock-commit-2')
            ]
        );

        return [
            $scopedParameters,
            ...parent::getQueryParameters(),
            $carryforwardParameters
        ];
    }

    #[DataProvider('resultsDataProvider')]
    public function testParseResults(array $queryResult): void
    {
        $mockBigQueryResult = $this->createMock(QueryResults::class);
        $mockBigQueryResult->expects($this->once())
            ->method('isComplete')
            ->willReturn(true);
        $mockBigQueryResult->expects($this->once())
            ->method('rows')
            ->willReturn($queryResult);

        $result = $this->getQueryClass()
            ->parseResults($mockBigQueryResult);

        $this->assertInstanceOf(LineCoverageCollectionQueryResult::class, $result);
    }

    #[DataProvider('parametersDataProvider')]
    public function testValidateParameters(QueryParameterBag $parameters, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(QueryException::class);
        } else {
            $this->expectNotToPerformAssertions();
        }

        $this->getQueryClass()->validateParameters($parameters);
    }

    public static function resultsDataProvider(): array
    {
        return [
            [
                [
                    [
                        'fileName' => 'mock-file',
                        'lineNumber' => 1,
                        'state' => LineState::COVERED->value,
                    ],
                ],
            ],
            [
                [
                    [
                        'fileName' => 'mock-file',
                        'lineNumber' => 1,
                        'state' => LineState::COVERED->value,
                    ],
                    [
                        'fileName' => 'mock-file-2',
                        'lineNumber' => 2,
                        'state' => LineState::UNCOVERED->value,
                    ],
                    [
                        'fileName' => 'mock-fil-3',
                        'lineNumber' => 3,
                        'state' => LineState::PARTIAL->value,
                    ],
                ]
            ]
        ];
    }

    public static function parametersDataProvider(): array
    {
        return [
            [
                new QueryParameterBag(),
                false
            ],
            [
                QueryParameterBag::fromUpload(
                    Upload::from([
                        'provider' => Provider::GITHUB->value,
                        'owner' => 'mock-owner',
                        'repository' => 'mock-repository',
                        'commit' => 'mock-commit',
                        'uploadId' => 'mock-uploadId',
                        'ref' => 'mock-ref',
                        'parent' => [],
                        'tag' => 'mock-tag',
                    ])
                ),
                true
            ],
        ];
    }
}
