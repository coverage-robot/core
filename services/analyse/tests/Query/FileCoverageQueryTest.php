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
