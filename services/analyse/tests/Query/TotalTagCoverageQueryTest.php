<?php

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\TotalTagCoverageQuery;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Google\Cloud\BigQuery\QueryResults;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\Provider;
use Packages\Event\Model\Upload;
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
            SQL,
            <<<SQL
            WITH
              unnested AS (
                SELECT
                  upload.tag,
                  upload.commit,
                  fileName,
                  lineNumber,
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
        $upload = new Upload(
            'mock-uploadId',
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit',
            [],
            'mock-ref',
            'mock-project-root',
            null,
            new Tag('mock-tag', 'mock-commit'),
        );

        $carryforwardParameters = QueryParameterBag::fromEvent($upload);
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
                QueryParameterBag::fromEvent(
                    new Upload(
                        'mock-uuid',
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-commit',
                        ['mock-parent-commit'],
                        'mock-ref',
                        'mock-project-root',
                        null,
                        new Tag('mock-tag', 'mock-commit-1')
                    )
                ),
                true
            ],
        ];
    }
}
