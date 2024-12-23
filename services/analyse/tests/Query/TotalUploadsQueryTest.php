<?php

declare(strict_types=1);

namespace App\Tests\Query;

use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\TotalUploadsQuery;
use ArrayIterator;
use Override;
use Packages\Contracts\Provider\Provider;

final class TotalUploadsQueryTest extends AbstractQueryTestCase
{
    #[Override]
    public function getQueryClass(): string
    {
        return TotalUploadsQuery::class;
    }

    public static function getQueryResults(): array
    {
        return [
            new ArrayIterator([
                [
                    'successfulUploads' => ['1'],
                    'successfulTags' => [
                        [
                            'name' => 'tag-1',
                            'commit' => 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                            'successfullyUploadedLines' => [100],
                        ]
                    ],
                    'successfulIngestTimes' => [
                        '2023-09-09T12:00:00+0000'
                    ]
                ]
            ]),
            new ArrayIterator([
                [
                    'successfulUploads' => ['1', '2'],
                    'successfulTags' => [
                        [
                            'name' => 'tag-1',
                            'commit' => 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                            'successfullyUploadedLines' => [100],
                        ],
                        [
                            'name' => 'tag-2',
                            'commit' => 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                            'successfullyUploadedLines' => [100],
                        ]
                    ],
                    'successfulIngestTimes' => [
                        '2023-09-09T12:00:00+0000',
                        '2024-01-03T12:19:30'
                    ]
                ]
            ]),
            new ArrayIterator([
                [
                    'successfulUploads' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
                    'successfulTags' => [
                        [
                            'name' => 'tag-1',
                            'commit' => 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                            'successfullyUploadedLines' => [100],
                        ]
                    ],
                    'successfulIngestTimes' => [
                        '2023-09-09T12:00:00+0000'
                    ]
                ]
            ]),
            new ArrayIterator([
                [
                    'successfulUploads' => ['1', '2', '3', '4', '5', '6', '7', '8'],
                    'successfulTags' => [
                        [
                            'name' => 'tag-1',
                            'commit' => 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                            'successfullyUploadedLines' => [100],
                        ]
                    ],
                    'successfulIngestTimes' => [
                        '2023-09-09T12:00:00+0000'
                    ]
                ]
            ]),
            new ArrayIterator([
                [
                    'commit' => 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                    'successfulUploads' => [],
                    'successfulIngestTimes' => [],
                    'successfulTags' => []
                ]
            ])
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
                        projectId: '0193f0cd-ad49-7e14-b6d2-e88545efc889',
                        owner: 'mock-owner',
                        repository: 'mock-repository',
                        ref: 'mock-ref',
                        commit: 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
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
                    projectId: '0193f0cd-ad49-7e14-b6d2-e88545efc889',
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'f7e3cc3cc12c056ed8ece76216127ea1ae188d8a',
                    history: [],
                    diff: []
                )
            )
        ];
    }
}
