<?php

namespace App\Tests\Service\Carryforward;

use App\Enum\QueryParameter;
use App\Model\CarryforwardTag;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\Result\TagAvailabilityCollectionQueryResult;
use App\Query\Result\TagAvailabilityQueryResult;
use App\Query\Result\UploadedTagsCollectionQueryResult;
use App\Query\Result\UploadedTagsQueryResult;
use App\Query\UploadedTagsQuery;
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\History\CommitHistoryService;
use App\Service\QueryServiceInterface;
use DateTimeImmutable;
use Packages\Configuration\Mock\MockTagBehaviourServiceFactory;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CarryforwardTagServiceTest extends TestCase
{
    public function testNoTagsToCarryforward(): void
    {
        $mockQueryService = $this->createMock(QueryServiceInterface::class);
        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(
                UploadedTagsQuery::class,
                self::callback(
                    function (QueryParameterBag $queryParameterBag): bool {
                        $this->assertEquals('mock-owner', $queryParameterBag->get(QueryParameter::OWNER));
                        $this->assertEquals('mock-repository', $queryParameterBag->get(QueryParameter::REPOSITORY));
                        return true;
                    }
                )
            )
            ->willReturn(
                new UploadedTagsCollectionQueryResult(
                    [
                        new UploadedTagsQueryResult('tag-1'),
                        new UploadedTagsQueryResult('tag-2'),
                    ]
                )
            );

        $carryforwardTagService = new CarryforwardTagService(
            $mockQueryService,
            MockTagBehaviourServiceFactory::createMock(
                $this,
                [
                    'tag-1' => true,
                    'tag-2' => true
                ]
            ),
            new NullLogger()
        );

        $carryforwardTags = $carryforwardTagService->getTagsToCarryforward(
            new ReportWaypoint(
                provider: Provider::GITHUB,
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: [],
                diff: []
            ),
            [
                new Tag('tag-1', 'mock-commit', [1]),
                new Tag('tag-2', 'mock-commit', [1])
            ]
        );

        $this->assertEquals([], $carryforwardTags);
    }

    public function testTagToCarryforwardFromRecentCommit(): void
    {
        $mockQueryService = $this->createMock(QueryServiceInterface::class);
        $mockQueryService->expects($this->exactly(2))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                new UploadedTagsCollectionQueryResult(
                    [
                        new UploadedTagsQueryResult('tag-1'),
                        new UploadedTagsQueryResult('tag-2'),
                    ]
                ),
                new TagAvailabilityCollectionQueryResult(
                    [
                        new TagAvailabilityQueryResult(
                            'tag-1',
                            [
                                new CarryforwardTag(
                                    'tag-1',
                                    'mock-commit',
                                    [100],
                                    [new DateTimeImmutable('2024-01-03 00:00:00')]
                                )
                            ]
                        ),
                        new TagAvailabilityQueryResult(
                            'tag-2',
                            [
                                new CarryforwardTag(
                                    'tag-2',
                                    'mock-commit-3',
                                    [100],
                                    [new DateTimeImmutable('2024-01-02 00:00:00')]
                                ),
                                new CarryforwardTag(
                                    'tag-2',
                                    'mock-commit-2',
                                    [100],
                                    [new DateTimeImmutable('2024-01-01 00:00:00')]
                                )
                            ]
                        ),
                    ]
                )
            );

        $carryforwardTagService = new CarryforwardTagService(
            $mockQueryService,
            MockTagBehaviourServiceFactory::createMock(
                $this,
                [
                    'tag-1' => true,
                    'tag-2' => true
                ]
            ),
            new NullLogger()
        );

        $carryforwardTags = $carryforwardTagService->getTagsToCarryforward(
            new ReportWaypoint(
                provider: Provider::GITHUB,
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: static fn(ReportWaypoint $waypoint, int $page): array => match ($page) {
                    1 => [
                        [
                            'commit' => 'mock-commit',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-2',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-3',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ]
                    ],
                    default => [],
                },
                diff: []
            ),
            [
                new Tag('tag-1', 'mock-current-commit', [1]),
            ]
        );

        $this->assertEquals(
            [
                new CarryforwardTag(
                    'tag-2',
                    'mock-commit-2',
                    [100],
                    [new DateTimeImmutable('2024-01-01 00:00:00')]
                )
            ],
            $carryforwardTags
        );
    }

    public function testTagToCarryforwardWithIgnoreBehaviour(): void
    {
        $mockQueryService = $this->createMock(QueryServiceInterface::class);
        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                new UploadedTagsCollectionQueryResult(
                    [
                        new UploadedTagsQueryResult('tag-1'),
                        new UploadedTagsQueryResult('tag-2'),
                    ]
                ),
                new TagAvailabilityCollectionQueryResult(
                    [
                        new TagAvailabilityQueryResult(
                            'tag-1',
                            [
                                new CarryforwardTag(
                                    'tag-1',
                                    'mock-commit',
                                    [100],
                                    [new DateTimeImmutable('2024-01-03 00:00:00')]
                                )
                            ]
                        ),
                        new TagAvailabilityQueryResult(
                            'tag-2',
                            [
                                new CarryforwardTag(
                                    'tag-2',
                                    'mock-commit-3',
                                    [100],
                                    [new DateTimeImmutable('2024-01-02 00:00:00')]
                                ),
                                new CarryforwardTag(
                                    'tag-2',
                                    'mock-commit-2',
                                    [100],
                                    [new DateTimeImmutable('2024-01-01 00:00:00')]
                                )
                            ]
                        ),
                    ]
                )
            );

        $carryforwardTagService = new CarryforwardTagService(
            $mockQueryService,
            MockTagBehaviourServiceFactory::createMock(
                $this,
                [
                    'tag-1' => true,
                    'tag-2' => false
                ]
            ),
            new NullLogger()
        );

        $carryforwardTags = $carryforwardTagService->getTagsToCarryforward(
            new ReportWaypoint(
                provider: Provider::GITHUB,
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: static fn(ReportWaypoint $waypoint, int $page): array => match ($page) {
                    1 => [
                        [
                            'commit' => 'mock-commit',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-2',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-3',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ]
                    ],
                    default => [],
                },
                diff: []
            ),
            [
                new Tag('tag-1', 'mock-current-commit', [1])
            ]
        );

        $this->assertEquals(
            [],
            $carryforwardTags
        );
    }

    public function testTagsToCarryforwardFromMultiplePagesOfCommits(): void
    {
        $mockQueryService = $this->createMock(QueryServiceInterface::class);
        $mockQueryService->expects($this->exactly(4))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                new UploadedTagsCollectionQueryResult(
                    [
                        new UploadedTagsQueryResult('tag-1'),
                        new UploadedTagsQueryResult('tag-2'),
                    ]
                ),
                new TagAvailabilityCollectionQueryResult(
                    [
                        new TagAvailabilityQueryResult(
                            'tag-1',
                            [
                                new CarryforwardTag(
                                    'tag-1',
                                    'mock-commit',
                                    [100],
                                    [new DateTimeImmutable('2024-01-05 00:00:00')]
                                ),
                            ]
                        )
                    ]
                ),
                new TagAvailabilityCollectionQueryResult([]),
                new TagAvailabilityCollectionQueryResult(
                    [
                        new TagAvailabilityQueryResult(
                            'tag-2',
                            [
                                new CarryforwardTag(
                                    'tag-2',
                                    'mock-commit-8',
                                    [100],
                                    [new DateTimeImmutable('2024-01-04 00:00:00')]
                                ),
                            ]
                        ),
                        new TagAvailabilityQueryResult(
                            'tag-2',
                            [
                                new CarryforwardTag(
                                    'tag-2',
                                    'mock-commit-9',
                                    [100],
                                    [new DateTimeImmutable('2024-01-06 00:00:00')]
                                ),
                            ]
                        )
                    ]
                ),
            );

        $carryforwardTagService = new CarryforwardTagService(
            $mockQueryService,
            MockTagBehaviourServiceFactory::createMock(
                $this,
                [
                    'tag-1' => true,
                    'tag-2' => true
                ]
            ),
            new NullLogger()
        );

        $carryforwardTags = $carryforwardTagService->getTagsToCarryforward(
            new ReportWaypoint(
                provider: Provider::GITHUB,
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: static fn(ReportWaypoint $waypoint, int $page): array => match ($page) {
                    1 => [
                        [
                            'commit' => 'mock-commit',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-2',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-3',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        ...array_fill(
                            0,
                            CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 3,
                            [
                                'commit' => 'mock-commit-99',
                                'ref' => 'non-main-branch',
                                'merged' => false
                            ]
                        )
                    ],
                    2 => [
                        [
                            'commit' => 'mock-commit-4',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-5',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-6',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        ...array_fill(
                            0,
                            CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 3,
                            [
                                'commit' => 'mock-commit-99',
                                'ref' => 'non-main-branch',
                                        'merged' => false
                            ]
                        )
                    ],
                    3 => [
                        [
                            'commit' => 'mock-commit-7',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-8',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-9',
                            'ref' => 'main-branch',
                            'merged' => true
                        ]
                    ],
                    default => [],
                },
                diff: [],
                pullRequest: 3
            ),
            []
        );

        $this->assertEquals(
            [
                new CarryforwardTag(
                    'tag-1',
                    'mock-commit',
                    [100],
                    [new DateTimeImmutable('2024-01-05 00:00:00')]
                ),
                new CarryforwardTag(
                    'tag-2',
                    'mock-commit-8',
                    [100],
                    [new DateTimeImmutable('2024-01-04 00:00:00')]
                )
            ],
            $carryforwardTags
        );
    }

    public function testTagsToCarryforwardOutOfRangeOfCommits(): void
    {
        $mockQueryService = $this->createMock(QueryServiceInterface::class);
        $mockQueryService->expects($this->exactly(6))
            ->method('runQuery')
            ->willReturnOnConsecutiveCalls(
                new UploadedTagsCollectionQueryResult(
                    [
                        new UploadedTagsQueryResult('tag-1'),
                        new UploadedTagsQueryResult('tag-2'),
                    ]
                ),
                new TagAvailabilityCollectionQueryResult(
                    [
                        new TagAvailabilityQueryResult(
                            'tag-1',
                            [
                                new CarryforwardTag(
                                    'tag-1',
                                    'mock-commit',
                                    [100],
                                    [new DateTimeImmutable('2024-01-05 00:00:00')]
                                )
                            ]
                        )
                    ]
                ),
                new TagAvailabilityCollectionQueryResult([]),
                new TagAvailabilityCollectionQueryResult([]),
                new TagAvailabilityCollectionQueryResult([]),
                new TagAvailabilityCollectionQueryResult([]),
            );

        $carryforwardTagService = new CarryforwardTagService(
            $mockQueryService,
            MockTagBehaviourServiceFactory::createMock(
                $this,
                [
                    'tag-1' => true,
                    'tag-2' => true
                ]
            ),
            new NullLogger()
        );

        $carryforwardTags = $carryforwardTagService->getTagsToCarryforward(
            new ReportWaypoint(
                provider: Provider::GITHUB,
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: static fn(ReportWaypoint $waypoint, int $page): array => match ($page) {
                    1 => [
                        [
                            'commit' => 'mock-commit',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-2',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-3',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        ...array_fill(
                            0,
                            CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 3,
                            [
                                'commit' => 'mock-commit-99',
                                'ref' => 'non-main-branch',
                                'merged' => false
                            ]
                        )
                    ],
                    2 => [
                        [
                            'commit' => 'mock-commit-4',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-5',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-6',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        ...array_fill(
                            0,
                            CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 3,
                            [
                                'commit' => 'mock-commit-99',
                                'ref' => 'non-main-branch',
                                'merged' => false
                            ]
                        )
                    ],
                    3 => [
                        [
                            'commit' => 'mock-commit-7',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-8',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-9',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        ...array_fill(
                            0,
                            CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 3,
                            [
                                'commit' => 'mock-commit-99',
                                'ref' => 'non-main-branch',
                                'merged' => false
                            ]
                        )
                    ],
                    4 => [
                        [
                            'commit' => 'mock-commit-10',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        [
                            'commit' => 'mock-commit-11',
                            'ref' => 'non-main-branch',
                            'merged' => false
                        ],
                        [
                            'commit' => 'mock-commit-12',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        ...array_fill(
                            0,
                            CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 3,
                            [
                                'commit' => 'mock-commit-99',
                                'ref' => 'non-main-branch',
                                'merged' => false
                            ]
                        )
                    ],
                    5 => [
                        [
                            'commit' => 'mock-commit-13',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        [
                            'commit' => 'mock-commit-14',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        [
                            'commit' => 'mock-commit-15',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        ...array_fill(
                            0,
                            CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 3,
                            [
                                'commit' => 'mock-commit-99',
                                'ref' => 'non-main-branch',
                                'merged' => false
                            ]
                        )
                    ],
                    default => [],
                },
                diff: []
            ),
            []
        );

        $this->assertEquals(
            [
                new CarryforwardTag(
                    'tag-1',
                    'mock-commit',
                    [100],
                    [new DateTimeImmutable('2024-01-05 00:00:00')]
                )
            ],
            $carryforwardTags
        );
    }
}
