<?php

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\CarryforwardTag;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\FileCoverageQuery;
use App\Query\QueryInterface;
use App\Query\Result\FileCoverageCollectionQueryResult;
use DateTimeImmutable;
use Google\Cloud\BigQuery\QueryResults;
use Override;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\SerializerInterface;

class FileCoverageQueryTest extends AbstractQueryTestCase
{
    #[Override]
    public function getQueryClass(): QueryInterface
    {
        return new FileCoverageQuery(
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::createMock(
                $this,
                Environment::PRODUCTION,
                [
                    EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-line-coverage-table'
                ]
            )
        );
    }

    #[Override]
    public static function getQueryParameters(): array
    {
        $waypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: [],
            pullRequest: 12
        );

        $lineScopedParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(QueryParameter::UPLOADS_SCOPE, ['1','2'])
            ->set(QueryParameter::INGEST_TIME_SCOPE, [
                new DateTimeImmutable('2024-01-03 00:00:00'),
                new DateTimeImmutable('2024-01-03 00:00:00')
            ])
            ->set(
                QueryParameter::LINE_SCOPE,
                [
                    'mock-file' => [1, 2, 3],
                    'mock-file-2' => [10, 11, 12]
                ]
            );

        $limitedParameters = (clone $lineScopedParameters)
            ->set(QueryParameter::LIMIT, 50);

        $carryforwardParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(QueryParameter::UPLOADS_SCOPE, [])
            ->set(QueryParameter::INGEST_TIME_SCOPE, [])
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                [
                    new CarryforwardTag('1', 'mock-commit', [new DateTimeImmutable('2024-01-03 00:00:00')]),
                    new CarryforwardTag('2', 'mock-commit', [new DateTimeImmutable('2024-01-03 00:00:00')]),
                    new CarryforwardTag('3', 'mock-commit-2', [new DateTimeImmutable('2024-01-01 02:00:00')]),
                    new CarryforwardTag('4', 'mock-commit-2', [new DateTimeImmutable('2024-01-01 02:00:00')])
                ]
            );

        return [
            $lineScopedParameters,
            $limitedParameters,
            $carryforwardParameters
        ];
    }

    #[DataProvider('parametersDataProvider')]
    #[Override]
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
    #[Override]
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
        $waypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: []
        );

        return [
            [
                new QueryParameterBag(),
                false
            ],
            [
                QueryParameterBag::fromWaypoint($waypoint)
                    ->set(QueryParameter::UPLOADS_SCOPE, ['1','2']),
                false
            ],
            [
                QueryParameterBag::fromWaypoint($waypoint)
                    ->set(QueryParameter::INGEST_TIME_SCOPE, ['1','2']),
                false
            ],
            [
                QueryParameterBag::fromWaypoint($waypoint)
                    ->set(QueryParameter::UPLOADS_SCOPE, ['1','2'])
                    ->set(
                        QueryParameter::INGEST_TIME_SCOPE,
                        [
                            new DateTimeImmutable('2024-01-03 00:00:00'),
                            new DateTimeImmutable('2024-01-03 00:00:00')
                        ]
                    ),
                true
            ],
            [
                QueryParameterBag::fromWaypoint($waypoint)
                    ->set(QueryParameter::UPLOADS_SCOPE, [])
                    ->set(QueryParameter::INGEST_TIME_SCOPE, []),
                false
            ],
            [
                QueryParameterBag::fromWaypoint($waypoint)
                    ->set(
                        QueryParameter::CARRYFORWARD_TAGS,
                        [
                            new CarryforwardTag(
                                '1',
                                'mock-commit',
                                [new DateTimeImmutable('2024-01-03 00:00:00')]
                            )
                        ]
                    ),
                true
            ],
        ];
    }
}
