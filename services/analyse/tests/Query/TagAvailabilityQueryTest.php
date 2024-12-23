<?php

declare(strict_types=1);

namespace App\Tests\Query;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\TagAvailabilityQuery;
use Override;
use Packages\Contracts\Provider\Provider;

final class TagAvailabilityQueryTest extends AbstractQueryTestCase
{
    #[Override]
    public function getQueryClass(): string
    {
        return TagAvailabilityQuery::class;
    }

    #[Override]
    public static function getQueryParameters(): array
    {
        return [
            QueryParameterBag::fromWaypoint(
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: '0193f0cd-ad49-7e14-b6d2-e88545efc889',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                    history: [],
                    diff: []
                )
            )->set(
                QueryParameter::COMMIT,
                ['a6e3cc3cc23c066de8ece76216127df1bd273d9f', 'b8e3cc3cc23c066ec8ece76214227df1ae188d9f']
            ),
        ];
    }

    public static function getQueryResults(): Iterator
    {
        yield [
            [
                [
                    'tagName' => 'mock-tag',
                    'carryforwardTags' => [
                        [
                            'name' => 'mock-tag',
                            'commit' => 'b4e1ee2cc12d033aa8aef76216127aa2ae177d8a',
                            'successfullyUploadedLines' => [100],
                            'ingestTimes' => [
                                '2023-09-09T12:00:00+0000'
                            ]
                        ],
                        [
                            'name' => 'mock-tag',
                            'commit' => 'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a',
                            'successfullyUploadedLines' => [100],
                            'ingestTimes' => [
                                '2023-09-11T12:00:00+0000',
                                '2023-09-11T12:00:00+0000'
                            ]
                        ]
                    ]
                ]
            ],
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
                            'commit' => 'a6e3dd3cc12d024ed8aef76216127aa2ae188d8a',
                            'successfullyUploadedLines' => [2, 100],
                            'ingestTimes' => [
                                '2023-09-11T12:00:00+0000',
                                '2023-09-11T12:00:00+0000'
                            ]
                        ]
                    ]
                ]
            ],
            [
                [
                    'tagName' => 'mock-tag-2',
                    'carryforwardTags' => [
                        [
                            'name' => 'mock-tag-2',
                            'commit' => 'a6e3dd3dc1a3045ed8aef6ea24127aa2ae188d8a',
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
        ];
    }

    public static function parametersDataProvider(): Iterator
    {
        yield [
            new QueryParameterBag(),
            false
        ];

        yield [
            QueryParameterBag::fromWaypoint(
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: '0193f0cd-ad49-7e14-b6d2-e88545efc889',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                    history: [],
                    diff: []
                )
            ),
            false
        ];

        yield [
            QueryParameterBag::fromWaypoint(
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    projectId: '0193f0cd-ad49-7e14-b6d2-e88545efc889',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                    history: [],
                    diff: []
                )
            )->set(
                    QueryParameter::COMMIT,
                    ['a6e3cc3cc23c066de8ece76216127df1bd273d9f', 'b8e3cc3cc23c066ec8ece76214227df1ae188d9f']
                ),
            true
        ];
    }
}
