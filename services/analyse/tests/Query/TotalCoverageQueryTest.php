<?php

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Query\QueryInterface;
use App\Query\Result\CoverageQueryResult;
use App\Query\TotalCoverageQuery;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Iterator\ItemIterator;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;

class TotalCoverageQueryTest extends AbstractQueryTestCase
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
                  `mock-table` as lines
                WHERE
                  (
                    (
                      commit = "mock-commit"
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
              ),
              summedCoverage AS (
                SELECT
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
                FROM
                  lines
              )
            SELECT
              SUM(lines) as lines,
              SUM(covered) as covered,
              SUM(partial) as partial,
              SUM(uncovered) as uncovered,
              ROUND(
                (
                  SUM(covered) + SUM(partial)
                ) / IF(
                  SUM(lines) = 0,
                  1,
                  SUM(lines)
                ) * 100,
                2
              ) as coveragePercentage
            FROM
              summedCoverage
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
                  `mock-table` as lines
                WHERE
                  (
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
              ),
              summedCoverage AS (
                SELECT
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
                FROM
                  lines
              )
            SELECT
              SUM(lines) as lines,
              SUM(covered) as covered,
              SUM(partial) as partial,
              SUM(uncovered) as uncovered,
              ROUND(
                (
                  SUM(covered) + SUM(partial)
                ) / IF(
                  SUM(lines) = 0,
                  1,
                  SUM(lines)
                ) * 100,
                2
              ) as coveragePercentage
            FROM
              summedCoverage
            SQL
        ];
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
            $carryforwardParameters,
        ];
    }

    public function getQueryClass(): QueryInterface
    {
        return new TotalCoverageQuery(
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::PRODUCTION,
                [
                    EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-line-coverage-table'
                ]
            )
        );
    }

    #[DataProvider('resultsDataProvider')]
    public function testParseResults(array $queryResult): void
    {
        $mockIterator = $this->createMock(ItemIterator::class);
        $mockIterator->expects($this->once())
            ->method('current')
            ->willReturn($queryResult);

        $mockBigQueryResult = $this->createMock(QueryResults::class);
        $mockBigQueryResult->expects($this->once())
            ->method('isComplete')
            ->willReturn(true);
        $mockBigQueryResult->expects($this->once())
            ->method('rows')
            ->willReturn($mockIterator);

        $result = $this->getQueryClass()
            ->parseResults($mockBigQueryResult);

        $this->assertInstanceOf(CoverageQueryResult::class, $result);
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
                    'lines' => 1,
                    'covered' => 1,
                    'partial' => 0,
                    'uncovered' => 0,
                    'coveragePercentage' => 100.0,
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
                    )
                ),
                true
            ],
        ];
    }
}
