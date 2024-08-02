<?php

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\QueryInterface;
use App\Query\Result\TagAvailabilityCollectionQueryResult;
use App\Query\TagAvailabilityQuery;
use Google\Cloud\BigQuery\QueryResults;
use Override;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\SerializerInterface;

final class TagAvailabilityQueryTest extends AbstractQueryTestCase
{
    #[Override]
    public function getQueryClass(): QueryInterface
    {
        return new TagAvailabilityQuery(
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::createMock(
                Environment::PRODUCTION,
                [
                    EnvironmentVariable::BIGQUERY_UPLOAD_TABLE->value => 'mock-table'
                ]
            )
        );
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

        $this->assertInstanceOf(TagAvailabilityCollectionQueryResult::class, $result);
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

    #[Override]
    public static function getQueryParameters(): array
    {
        return [
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
            )->set(QueryParameter::COMMIT, ['1', '2']),
        ];
    }

    public static function resultsDataProvider(): array
    {
        return [
            [
                [
                    [
                        'tagName' => 'mock-tag',
                        'carryforwardTags' => [
                            [
                                'name' => 'mock-tag',
                                'commit' => 'mock-commit-1',
                                'successfullyUploadedLines' => [100],
                                'ingestTimes' => [
                                    '2023-09-09T12:00:00+0000'
                                ]
                            ],
                            [
                                'name' => 'mock-tag',
                                'commit' => 'mock-commit-2',
                                'successfullyUploadedLines' => [100],
                                'ingestTimes' => [
                                    '2023-09-11T12:00:00+0000',
                                    '2023-09-11T12:00:00+0000'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                [
                    [
                        'tagName' => 'mock-tag',
                        'carryforwardTags' => [
                            [
                                'name' => 'mock-tag',
                                'commit' => 'mock-commit-1',
                                'successfullyUploadedLines' => [100],
                                'ingestTimes' => [
                                    '2023-09-09T12:00:00+0000'
                                ]
                            ],
                            [
                                'name' => 'mock-tag',
                                'commit' => 'mock-commit-2',
                                'successfullyUploadedLines' => [2, 100],
                                'ingestTimes' => [
                                    '2023-09-11T12:00:00+0000',
                                    '2023-09-11T12:00:00+0000'
                                ]
                            ]
                        ]
                    ],
                    [
                        'tagName' => 'mock-tag-2',
                        'carryforwardTags' => [
                            [
                                'name' => 'mock-tag-2',
                                'commit' => 'mock-commit-3',
                                'successfullyUploadedLines' => [100, 200],
                                'ingestTimes' => [
                                    '2023-09-09T12:00:00+0000'
                                ]
                            ],
                            [
                                'name' => 'mock-tag-2',
                                'commit' => 'mock-commit-4',
                                'successfullyUploadedLines' => [100, 1],
                                'ingestTimes' => [
                                    '2023-09-11T12:00:00+0000',
                                    '2023-09-11T12:00:00+0000'
                                ]
                            ]
                        ]
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
                )->set(QueryParameter::COMMIT, ['1', '2']),
                true
            ],
        ];
    }
}
