<?php

namespace App\Tests\Query;

use App\Enum\EnvironmentVariable;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\QueryInterface;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalUploadsQuery;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Iterator\ItemIterator;
use Override;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\SerializerInterface;

final class TotalUploadsQueryTest extends AbstractQueryTestCase
{
    #[Override]
    public function getQueryClass(): QueryInterface
    {
        return new TotalUploadsQuery(
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::createMock(
                $this,
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
        $mockIterator = $this->createMock(ItemIterator::class);
        $mockIterator->expects($this->once())
            ->method('current')
            ->willReturn($queryResult);

        $mockBigQueryResult = $this->createMock(QueryResults::class);
        $mockBigQueryResult->expects($this->once())
            ->method('rows')
            ->willReturn($mockIterator);

        $result = $this->getQueryClass()
            ->parseResults($mockBigQueryResult);

        $this->assertInstanceOf(TotalUploadsQueryResult::class, $result);
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
                    'successfulUploads' => ['1'],
                    'successfulTags' => [
                        [
                            'name' => 'tag-1',
                            'commit' => 'mock-commit'
                        ]
                    ],
                    'successfulIngestTimes' => [
                        '2023-09-09T12:00:00+0000'
                    ]
                ]
            ],
            [
                [
                    'successfulUploads' => ['1', '2'],
                    'successfulTags' => [
                        [
                            'name' => 'tag-1',
                            'commit' => 'mock-commit'
                        ],
                        [
                            'name' => 'tag-2',
                            'commit' => 'mock-commit'
                        ]
                    ],
                    'successfulIngestTimes' => [
                        '2023-09-09T12:00:00+0000',
                        '2024-01-03T12:19:30'
                    ]
                ]
            ],
            [
                [
                    'successfulUploads' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
                    'successfulTags' => [
                        [
                            'name' => 'tag-1',
                            'commit' => 'mock-commit'
                        ]
                    ],
                    'successfulIngestTimes' => [
                        '2023-09-09T12:00:00+0000'
                    ]
                ],
            ],
            [
                [
                    'successfulUploads' => ['1', '2', '3', '4', '5', '6', '7', '8'],
                    'successfulTags' => [
                        [
                            'name' => 'tag-1',
                            'commit' => 'mock-commit'
                        ]
                    ],
                    'successfulIngestTimes' => [
                        '2023-09-09T12:00:00+0000'
                    ]
                ]
            ],
            [
                [
                    'commit' => 'mock-commit',
                    'successfulUploads' => [],
                    'successfulIngestTimes' => [],
                    'successfulTags' => []
                ]
            ],
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
            )
        ];
    }
}
