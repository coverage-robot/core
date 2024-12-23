<?php

declare(strict_types=1);

namespace App\Tests\Query;

use App\Enum\QueryParameter;
use App\Model\CarryforwardTag;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\TotalTagCoverageQuery;
use ArrayIterator;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Provider\Provider;

final class TotalTagCoverageQueryTest extends AbstractQueryTestCase
{
    #[Override]
    public function getQueryClass(): string
    {
        return TotalTagCoverageQuery::class;
    }

    #[Override]
    public static function getQueryParameters(): array
    {
        $waypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: '0193f0cd-ad49-7e14-b6d2-e88545efc889',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
            history: [],
            diff: []
        );

        $lineScopedParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::UPLOADS,
                ['0193f0c5-bae7-7b67-bb26-81e781146de8', '0193f0c5-d84f-7470-a008-97c2b9538933']
            )
            ->set(
                QueryParameter::INGEST_PARTITIONS,
                [
                    new DateTimeImmutable('2024-01-03 00:00:00'),
                    new DateTimeImmutable('2024-01-03 00:00:00')
                ]
            )
            ->set(
                QueryParameter::LINES,
                [
                    '1' => [1, 2, 3],
                    '2' => [1, 2, 3],
                ]
            );

        $carryforwardParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                [
                    new CarryforwardTag(
                        '1',
                        'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                        [110],
                        [new DateTimeImmutable('2024-01-03 00:00:00')]
                    ),
                    new CarryforwardTag(
                        '2',
                        'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                        [110],
                        [new DateTimeImmutable('2024-01-03 00:00:00')]
                    ),
                    new CarryforwardTag(
                        '3',
                        'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a',
                        [110],
                        [new DateTimeImmutable('2024-01-01 02:00:00')]
                    ),
                    new CarryforwardTag('4', 'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a', [110], [
                        new DateTimeImmutable(
                            '2024-01-01 02:00:00'
                        )
                    ])
                ]
            );


        $carryforwardAndUploadParameters = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::UPLOADS,
                ['0193f0c5-bae7-7b67-bb26-81e781146de8', '0193f0c5-d84f-7470-a008-97c2b9538933']
            )
            ->set(
                QueryParameter::INGEST_PARTITIONS,
                [
                    new DateTimeImmutable('2024-01-03 00:00:00'),
                    new DateTimeImmutable('2024-01-03 00:00:00')
                ]
            )
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                [
                    new CarryforwardTag(
                        '1',
                        'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                        [110],
                        [new DateTimeImmutable('2024-01-03 00:00:00')]
                    ),
                    new CarryforwardTag(
                        '2',
                        'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                        [110],
                        [new DateTimeImmutable('2024-01-03 00:00:00')]
                    ),
                    new CarryforwardTag(
                        '3',
                        'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a',
                        [110],
                        [new DateTimeImmutable('2024-01-01 02:00:00')]
                    ),
                    new CarryforwardTag('4', 'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a', [110], [
                        new DateTimeImmutable(
                            '2024-01-01 02:00:00'
                        )
                    ])
                ]
            );

        return [
            $lineScopedParameters,
            $carryforwardParameters,
            $carryforwardAndUploadParameters
        ];
    }

    public static function getQueryResults(): array
    {
        return [
            new ArrayIterator([
                [
                    'tag' => [
                        'name' => '1',
                        'commit' => 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                        'successfullyUploadedLines' => [110],
                    ],
                    'lines' => 1,
                    'covered' => 1,
                    'partial' => 0,
                    'uncovered' => 0,
                    'coveragePercentage' => 100.0
                ],
            ]),
            new ArrayIterator([
                [
                    'tag' => [
                        'name' => '2',
                        'commit' => 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                        'successfullyUploadedLines' => [100],
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
                        'commit' => 'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a',
                        'successfullyUploadedLines' => [90],
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
                        'commit' => 'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a',
                        'successfullyUploadedLines' => [80],
                    ],
                    'lines' => 1,
                    'covered' => 0,
                    'partial' => 0,
                    'uncovered' => 1,
                    'coveragePercentage' => 0.0
                ]
            ])
        ];
    }

    public static function parametersDataProvider(): array
    {
        $waypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: '0193f0cd-ad49-7e14-b6d2-e88545efc889',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
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
                    ->set(
                        QueryParameter::UPLOADS,
                        ['0193f0c5-bae7-7b67-bb26-81e781146de8', '0193f0c5-d84f-7470-a008-97c2b9538933']
                    ),
                false
            ],
            [
                QueryParameterBag::fromWaypoint($waypoint)
                    ->set(QueryParameter::INGEST_PARTITIONS, [new DateTimeImmutable(), new DateTimeImmutable()]),
                false
            ],
            [
                QueryParameterBag::fromWaypoint($waypoint)
                    ->set(
                        QueryParameter::UPLOADS,
                        ['0193f0c5-bae7-7b67-bb26-81e781146de8', '0193f0c5-d84f-7470-a008-97c2b9538933']
                    )
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
                    ->set(
                        QueryParameter::CARRYFORWARD_TAGS,
                        [
                            new CarryforwardTag(
                                '1',
                                'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                                [110],
                                [new DateTimeImmutable('2024-01-03 00:00:00')]
                            )
                        ]
                    ),
                true
            ],
        ];
    }
}
