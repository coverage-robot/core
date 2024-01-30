<?php

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\CarryforwardTag;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\LineCoverageQuery;
use App\Query\QueryInterface;
use App\Query\Result\LineCoverageCollectionQueryResult;
use DateTimeImmutable;
use Google\Cloud\BigQuery\QueryResults;
use Override;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Line\LineState;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\SerializerInterface;

final class LineCoverageQueryTest extends AbstractQueryTestCase
{
    #[Override]
    public function getQueryClass(): QueryInterface
    {
        return new LineCoverageQuery(
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

        $scopedParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(QueryParameter::UPLOADS, ['1','2'])
            ->set(QueryParameter::INGEST_PARTITIONS, [
                new DateTimeImmutable('2024-01-03 00:00:00'),
                new DateTimeImmutable('2024-01-03 00:00:00')
            ])
            ->set(
                QueryParameter::LINES,
                [
                    'mock-file' => [1, 2, 3],
                    'mock-file-2' => [10, 11, 12]
                ]
            );

        $carryforwardParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(QueryParameter::UPLOADS, [])
            ->set(QueryParameter::INGEST_PARTITIONS, [])
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                [
                    new CarryforwardTag('1', 'mock-commit', [new DateTimeImmutable('2024-01-03 00:00:00')]),
                    new CarryforwardTag('2', 'mock-commit', [new DateTimeImmutable('2024-01-03 00:00:00')]),
                    new CarryforwardTag('3', 'mock-commit-2', [new DateTimeImmutable('2024-01-01 02:00:00')]),
                    new CarryforwardTag('4', 'mock-commit-2', [new DateTimeImmutable('2024-01-01 02:00:00')])
                ]
            );

        $carryforwardAndUploadsParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(QueryParameter::UPLOADS, ['1','2'])
            ->set(QueryParameter::INGEST_PARTITIONS, [
                new DateTimeImmutable('2024-01-03 00:00:00'),
                new DateTimeImmutable('2024-01-03 00:00:00')
            ])
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
            $scopedParameters,
            $carryforwardParameters,
            $carryforwardAndUploadsParameters
        ];
    }

    #[DataProvider('resultsDataProvider')]
    #[Override]
    public function testParseResults(array $queryResult): void
    {
        $mockBigQueryResult = $this->createMock(QueryResults::class);
        $mockBigQueryResult->expects($this->once())
            ->method('rows')
            ->willReturn($queryResult);

        $result = $this->getQueryClass()
            ->parseResults($mockBigQueryResult);

        $this->assertInstanceOf(LineCoverageCollectionQueryResult::class, $result);
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
                        'fileName' => 'mock-file',
                        'lineNumber' => 1,
                        'state' => LineState::COVERED->value,
                        'containsMethod' => false,
                        'containsBranch' => false,
                        'containsStatement' => true,
                        'totalBranches' => 0,
                        'coveredBranches' => 0,
                    ],
                ],
            ],
            [
                [
                    [
                        'fileName' => 'mock-file',
                        'lineNumber' => 1,
                        'state' => LineState::COVERED->value,
                        'containsMethod' => false,
                        'containsBranch' => false,
                        'containsStatement' => true,
                        'totalBranches' => 0,
                        'coveredBranches' => 0,
                    ],
                    [
                        'fileName' => 'mock-file-2',
                        'lineNumber' => 2,
                        'state' => LineState::UNCOVERED->value,
                        'containsMethod' => false,
                        'containsBranch' => false,
                        'containsStatement' => true,
                        'totalBranches' => 0,
                        'coveredBranches' => 0,
                    ],
                    [
                        'fileName' => 'mock-file-3',
                        'lineNumber' => 3,
                        'state' => LineState::PARTIAL->value,
                        'containsMethod' => false,
                        'containsBranch' => false,
                        'containsStatement' => true,
                        'totalBranches' => 0,
                        'coveredBranches' => 0,
                    ],
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
                    ->set(QueryParameter::UPLOADS, ['1','2']),
                false
            ],
            [
                QueryParameterBag::fromWaypoint($waypoint)
                    ->set(QueryParameter::INGEST_PARTITIONS, ['1','2']),
                false
            ],
            [
                QueryParameterBag::fromWaypoint($waypoint)
                    ->set(QueryParameter::UPLOADS, ['1','2'])
                    ->set(
                        QueryParameter::INGEST_PARTITIONS,
                        [
                            new DateTimeImmutable('2024-01-03 00:00:00'),
                            new DateTimeImmutable('2024-01-03 00:00:00')
                        ]
                    ),
                true
            ],
            [
                QueryParameterBag::fromWaypoint($waypoint)
                    ->set(QueryParameter::UPLOADS, [])
                    ->set(QueryParameter::INGEST_PARTITIONS, []),
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
