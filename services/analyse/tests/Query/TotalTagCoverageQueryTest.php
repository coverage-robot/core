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
use Override;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\SerializerInterface;

class TotalTagCoverageQueryTest extends AbstractQueryTestCase
{
    #[Override]
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
        return [
            [
                new QueryParameterBag(),
                false
            ],
            [
                QueryParameterBag::fromWaypoint(
                    new ReportWaypoint(
                        provider: Provider::GITHUB,
                        owner: 'mock-owner',
                        repository: 'mock-repository',
                        ref: 'mock-ref',
                        commit: 'mock-commit',
                        history: [],
                        diff: []
                    )
                ),
                true
            ],
        ];
    }
}
