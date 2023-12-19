<?php

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\QueryInterface;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\TotalTagCoverageQuery;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Google\Cloud\BigQuery\QueryResults;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\SerializerInterface;

class TotalTagCoverageQueryTest extends AbstractQueryTestCase
{
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
                    upload.commit = "mock-commit"
                    AND upload.repository = "mock-repository"
                    AND upload.owner = "mock-owner"
                    AND upload.provider = "github"
                  )
              ),
              branchingLines AS (
                SELECT
                  fileName,
                  lineNumber,
                  tag,
                  commit,
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
                  tag,
                  commit,
                  fileName,
                  lineNumber
              )
            SELECT
              tag as tagName,
              STRUCT(tag as name, commit as commit) as tag,
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
              tagName,
              commit
            ORDER BY
              tagName ASC
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
              ),
              branchingLines AS (
                SELECT
                  fileName,
                  lineNumber,
                  tag,
                  commit,
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
                  tag,
                  commit,
                  fileName,
                  lineNumber
              )
            SELECT
              tag as tagName,
              STRUCT(tag as name, commit as commit) as tag,
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
              tagName,
              commit
            ORDER BY
              tagName ASC
            SQL
        ];
    }

    public function getQueryClass(): QueryInterface
    {
        return new TotalTagCoverageQuery(
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

    public static function getQueryParameters(): array
    {
        $waypoint = new ReportWaypoint(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            null,
            [],
            []
        );

        $carryforwardParameters = QueryParameterBag::fromWaypoint($waypoint);
        $carryforwardParameters->set(QueryParameter::CARRYFORWARD_TAGS, [
            new Tag('1', 'mock-commit'),
            new Tag('2', 'mock-commit'),
            new Tag('3', 'mock-commit-2'),
            new Tag('4', 'mock-commit-2')
        ]);
        return [
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

        $this->assertInstanceOf(TagCoverageCollectionQueryResult::class, $result);
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
                        'tag' => [
                            'name' => '1',
                            'commit' => 'mock-commit',
                        ],
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
                        'tag' => [
                            'name' => '2',
                            'commit' => 'mock-commit',
                        ],
                        'lines' => 1,
                        'covered' => 0,
                        'partial' => 1,
                        'uncovered' => 0,
                        'coveragePercentage' => 0.0
                    ],
                    [
                        'tag' => [
                            'name' => '3',
                            'commit' => 'mock-commit-2',
                        ],
                        'lines' => 1,
                        'covered' => 0,
                        'partial' => 0,
                        'uncovered' => 1,
                        'coveragePercentage' => 0.0
                    ],
                    [
                        'tag' => [
                            'name' => '4',
                            'commit' => 'mock-commit-2',
                        ],
                        'lines' => 1,
                        'covered' => 0,
                        'partial' => 0,
                        'uncovered' => 1,
                        'coveragePercentage' => 0.0
                    ]
                ],
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
