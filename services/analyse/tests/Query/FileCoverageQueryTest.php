<?php

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\FileCoverageQuery;
use App\Query\QueryInterface;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Google\Cloud\BigQuery\QueryResults;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\SerializerInterface;

class FileCoverageQueryTest extends AbstractQueryTestCase
{
    public function getQueryClass(): QueryInterface
    {
        return new FileCoverageQuery(
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::PRODUCTION,
                [
                    EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-line-coverage-table'
                ]
            )
        );
    }

    /**
     * @inheritDoc
     */
    public static function getExpectedQueries(): array
    {
        return [
            <<<SQL
            WITH
              unnested AS (
                SELECT
                  upload.tag,
                  upload.commit,
                  fileName,
                  lineNumber,
                  type = 'METHOD' as containsMethod,
                  type = 'BRANCH' as containsBranch,
                  type = 'STATEMENT' as containsStatement,
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
                  `mock-table` as upload
                  INNER JOIN `mock-line-coverage-table` as lines ON lines.uploadId = upload.uploadId
                WHERE
                  (
                    (
                      upload.commit = "mock-commit"
                      AND upload.repository = "mock-repository"
                      AND upload.owner = "mock-owner"
                      AND upload.provider = "github"
                    )
                  )
                  AND (
                    (
                      lines.fileName = "mock-file"
                      AND lines.lineNumber IN (1, 2, 3)
                    )
                    OR(
                      lines.fileName = "mock-file-2"
                      AND lines.lineNumber IN (10, 11, 12)
                    )
                  )
              ),
              branchingLines AS (
                SELECT
                  fileName,
                  lineNumber,
                  MAX(containsMethod) as containsMethod,
                  MAX(containsBranch) as containsBranch,
                  MAX(containsStatement) as containsStatement,
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
                  MAX(containsMethod) as containsMethod,
                  MAX(containsBranch) as containsBranch,
                  MAX(containsStatement) as containsStatement,
                  COUNTIF(containsBranch = true) as totalBranches,
                  COUNTIF(
                    containsBranch = true
                    AND isBranchedLineHit = true
                  ) as coveredBranches,
                  IF(
                    -- Check that the line hits are 0 (i.e. not executed) and that, if theres a branch, it's
                    -- definitely not been covered at all (as we'll want to show that as a partial line)
                    SUM(hits) = 0
                    AND COUNTIF(
                      containsBranch = true
                      AND isBranchedLineHit = true
                    ) = 0,
                    "uncovered",
                    IF (
                      MIN(isBranchedLineHit) = false,
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
              fileName
            ORDER BY
              ROUND(
                (
                  SUM(
                    IF(state = "covered", 1, 0)
                  ) + SUM(
                    IF(state = "partial", 1, 0)
                  )
                ) / COUNT(*) * 100,
                2
              ) ASC
            SQL,
            <<<SQL
            WITH
              unnested AS (
                SELECT
                  upload.tag,
                  upload.commit,
                  fileName,
                  lineNumber,
                  type = 'METHOD' as containsMethod,
                  type = 'BRANCH' as containsBranch,
                  type = 'STATEMENT' as containsStatement,
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
                  `mock-table` as upload
                  INNER JOIN `mock-line-coverage-table` as lines ON lines.uploadId = upload.uploadId
                WHERE
                  (
                    (
                      upload.commit = "mock-commit"
                      AND upload.repository = "mock-repository"
                      AND upload.owner = "mock-owner"
                      AND upload.provider = "github"
                    )
                  )
                  AND (
                    (
                      lines.fileName = "mock-file"
                      AND lines.lineNumber IN (1, 2, 3)
                    )
                    OR(
                      lines.fileName = "mock-file-2"
                      AND lines.lineNumber IN (10, 11, 12)
                    )
                  )
              ),
              branchingLines AS (
                SELECT
                  fileName,
                  lineNumber,
                  MAX(containsMethod) as containsMethod,
                  MAX(containsBranch) as containsBranch,
                  MAX(containsStatement) as containsStatement,
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
                  MAX(containsMethod) as containsMethod,
                  MAX(containsBranch) as containsBranch,
                  MAX(containsStatement) as containsStatement,
                  COUNTIF(containsBranch = true) as totalBranches,
                  COUNTIF(
                    containsBranch = true
                    AND isBranchedLineHit = true
                  ) as coveredBranches,
                  IF(
                    -- Check that the line hits are 0 (i.e. not executed) and that, if theres a branch, it's
                    -- definitely not been covered at all (as we'll want to show that as a partial line)
                    SUM(hits) = 0
                    AND COUNTIF(
                      containsBranch = true
                      AND isBranchedLineHit = true
                    ) = 0,
                    "uncovered",
                    IF (
                      MIN(isBranchedLineHit) = false,
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
              fileName
            ORDER BY
              ROUND(
                (
                  SUM(
                    IF(state = "covered", 1, 0)
                  ) + SUM(
                    IF(state = "partial", 1, 0)
                  )
                ) / COUNT(*) * 100,
                2
              ) ASC
            LIMIT
              50
            SQL,
            <<<SQL
            WITH
              unnested AS (
                SELECT
                  upload.tag,
                  upload.commit,
                  fileName,
                  lineNumber,
                  type = 'METHOD' as containsMethod,
                  type = 'BRANCH' as containsBranch,
                  type = 'STATEMENT' as containsStatement,
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
                  `mock-table` as upload
                  INNER JOIN `mock-line-coverage-table` as lines ON lines.uploadId = upload.uploadId
                WHERE
                  (
                    (
                      upload.commit = "mock-commit"
                      AND upload.repository = "mock-repository"
                      AND upload.owner = "mock-owner"
                      AND upload.provider = "github"
                    )
                  )
              ),
              branchingLines AS (
                SELECT
                  fileName,
                  lineNumber,
                  MAX(containsMethod) as containsMethod,
                  MAX(containsBranch) as containsBranch,
                  MAX(containsStatement) as containsStatement,
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
                  MAX(containsMethod) as containsMethod,
                  MAX(containsBranch) as containsBranch,
                  MAX(containsStatement) as containsStatement,
                  COUNTIF(containsBranch = true) as totalBranches,
                  COUNTIF(
                    containsBranch = true
                    AND isBranchedLineHit = true
                  ) as coveredBranches,
                  IF(
                    -- Check that the line hits are 0 (i.e. not executed) and that, if theres a branch, it's
                    -- definitely not been covered at all (as we'll want to show that as a partial line)
                    SUM(hits) = 0
                    AND COUNTIF(
                      containsBranch = true
                      AND isBranchedLineHit = true
                    ) = 0,
                    "uncovered",
                    IF (
                      MIN(isBranchedLineHit) = false,
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
              fileName
            ORDER BY
              ROUND(
                (
                  SUM(
                    IF(state = "covered", 1, 0)
                  ) + SUM(
                    IF(state = "partial", 1, 0)
                  )
                ) / COUNT(*) * 100,
                2
              ) ASC
            SQL,
            <<<SQL
            WITH
              unnested AS (
                SELECT
                  upload.tag,
                  upload.commit,
                  fileName,
                  lineNumber,
                  type = 'METHOD' as containsMethod,
                  type = 'BRANCH' as containsBranch,
                  type = 'STATEMENT' as containsStatement,
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
                  `mock-table` as upload
                  INNER JOIN `mock-line-coverage-table` as lines ON lines.uploadId = upload.uploadId
                WHERE
                  (
                    (
                      upload.commit = "mock-commit"
                      AND upload.repository = "mock-repository"
                      AND upload.owner = "mock-owner"
                      AND upload.provider = "github"
                    )
                    OR (
                      (
                        (
                          upload.commit = "mock-commit"
                          AND upload.tag = "1"
                        )
                        OR (
                          upload.commit = "mock-commit"
                          AND upload.tag = "2"
                        )
                        OR (
                          upload.commit = "mock-commit-2"
                          AND upload.tag = "3"
                        )
                        OR (
                          upload.commit = "mock-commit-2"
                          AND upload.tag = "4"
                        )
                      )
                      AND upload.repository = "mock-repository"
                      AND upload.owner = "mock-owner"
                      AND upload.provider = "github"
                    )
                  )
              ),
              branchingLines AS (
                SELECT
                  fileName,
                  lineNumber,
                  MAX(containsMethod) as containsMethod,
                  MAX(containsBranch) as containsBranch,
                  MAX(containsStatement) as containsStatement,
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
                  MAX(containsMethod) as containsMethod,
                  MAX(containsBranch) as containsBranch,
                  MAX(containsStatement) as containsStatement,
                  COUNTIF(containsBranch = true) as totalBranches,
                  COUNTIF(
                    containsBranch = true
                    AND isBranchedLineHit = true
                  ) as coveredBranches,
                  IF(
                    -- Check that the line hits are 0 (i.e. not executed) and that, if theres a branch, it's
                    -- definitely not been covered at all (as we'll want to show that as a partial line)
                    SUM(hits) = 0
                    AND COUNTIF(
                      containsBranch = true
                      AND isBranchedLineHit = true
                    ) = 0,
                    "uncovered",
                    IF (
                      MIN(isBranchedLineHit) = false,
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
              fileName
            ORDER BY
              ROUND(
                (
                  SUM(
                    IF(state = "covered", 1, 0)
                  ) + SUM(
                    IF(state = "partial", 1, 0)
                  )
                ) / COUNT(*) * 100,
                2
              ) ASC
            SQL
        ];
    }

    public static function getQueryParameters(): array
    {
        $waypoint = new ReportWaypoint(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            12,
            [],
            []
        );

        $lineScope = [
            'mock-file' => [1, 2, 3],
            'mock-file-2' => [10, 11, 12]
        ];

        $lineScopedParameters = QueryParameterBag::fromWaypoint($waypoint);
        $lineScopedParameters->set(QueryParameter::LINE_SCOPE, $lineScope);

        $limitedParameters = QueryParameterBag::fromWaypoint($waypoint);
        $limitedParameters->set(QueryParameter::LINE_SCOPE, $lineScope);
        $limitedParameters->set(QueryParameter::LIMIT, 50);

        $carryforwardParameters = QueryParameterBag::fromWaypoint($waypoint);
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
            $lineScopedParameters,
            $limitedParameters,
            ...parent::getQueryParameters(),
            $carryforwardParameters
        ];
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

        $this->assertInstanceOf(FileCoverageCollectionQueryResult::class, $result);
    }

    public static function resultsDataProvider(): array
    {
        return [
            [
                [
                    [
                        'fileName' => 'mock-file',
                        'lines' => 1,
                        'covered' => 1,
                        'partial' => 0,
                        'uncovered' => 0,
                        'coveragePercentage' => 100.0
                    ],
                ],
            ],
            [
                [
                    [
                        'fileName' => 'mock-file',
                        'lines' => 1,
                        'covered' => 1,
                        'partial' => 0,
                        'uncovered' => 0,
                        'coveragePercentage' => 100.0
                    ],
                    [
                        'fileName' => 'mock-file-2',
                        'lines' => 10,
                        'covered' => 5,
                        'partial' => 0,
                        'uncovered' => 5,
                        'coveragePercentage' => 50.0
                    ]
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
                QueryParameterBag::fromWaypoint(
                    new ReportWaypoint(
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-ref',
                        'mock-commit',
                        null,
                        [],
                        []
                    )
                ),
                true
            ],
        ];
    }
}
