<?php

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\CarryforwardTag;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\QueryInterface;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\TotalTagCoverageQuery;
use DateTimeImmutable;
use Google\Cloud\BigQuery\QueryResults;
use Override;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\SerializerInterface;

class TotalTagCoverageQueryTest extends AbstractQueryTestCase
{
    #[Override]
    public function getQueryClass(): QueryInterface
    {
        return new TotalTagCoverageQuery(
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
            diff: []
        );

        $lineScopedParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(QueryParameter::UPLOADS_SCOPE, ['1','2'])
            ->set(
                QueryParameter::INGEST_TIME_SCOPE,
                [
                    new DateTimeImmutable('2024-01-03 00:00:00'),
                    new DateTimeImmutable('2024-01-03 00:00:00')
                ]
            )
            ->set(
                QueryParameter::LINE_SCOPE,
                [
                    '1' => [1, 2, 3],
                    '2' => [1, 2, 3],
                ]
            );

        $carryforwardParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                [
                    new CarryforwardTag('1', 'mock-commit', [new DateTimeImmutable('2024-01-03 00:00:00')]),
                    new CarryforwardTag('2', 'mock-commit', [new DateTimeImmutable('2024-01-03 00:00:00')]),
                    new CarryforwardTag('3', 'mock-commit-2', [new DateTimeImmutable('2024-01-01 02:00:00')]),
                    new CarryforwardTag('4', 'mock-commit-2', [new DateTimeImmutable('2024-01-01 02:00:00')])
                ]
            );


        $carryforwardAndUploadParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(QueryParameter::UPLOADS_SCOPE, ['1','2'])
            ->set(
                QueryParameter::INGEST_TIME_SCOPE,
                [
                    new DateTimeImmutable('2024-01-03 00:00:00'),
                    new DateTimeImmutable('2024-01-03 00:00:00')
                ]
            )
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
            $carryforwardParameters,
            $carryforwardAndUploadParameters
        ];
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

        $this->assertInstanceOf(TagCoverageCollectionQueryResult::class, $result);
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
