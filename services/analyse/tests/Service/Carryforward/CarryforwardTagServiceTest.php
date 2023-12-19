<?php

namespace App\Tests\Service\Carryforward;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\Result\TagAvailabilityCollectionQueryResult;
use App\Query\Result\TagAvailabilityQueryResult;
use App\Query\TagAvailabilityQuery;
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\History\CommitHistoryService;
use App\Service\QueryService;
use Packages\Contracts\Provider\Provider;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CarryforwardTagServiceTest extends TestCase
{
    public function testNoTagsToCarryforward(): void
    {
        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(
                TagAvailabilityQuery::class,
                self::callback(
                    function (QueryParameterBag $queryParameterBag) {
                        $this->assertEquals('mock-owner', $queryParameterBag->get(QueryParameter::OWNER));
                        $this->assertEquals('mock-repository', $queryParameterBag->get(QueryParameter::REPOSITORY));
                        return true;
                    }
                )
            )
            ->willReturn(
                new TagAvailabilityCollectionQueryResult(
                    [
                        new TagAvailabilityQueryResult('tag-1', ['mock-commit']),
                        new TagAvailabilityQueryResult('tag-2', ['mock-commit-3', 'mock-commit-2']),
                    ]
                )
            );

        $carryforwardTagService = new CarryforwardTagService(
            $mockQueryService,
            new NullLogger()
        );

        $carryforwardTags = $carryforwardTagService->getTagsToCarryforward(
            new ReportWaypoint(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-ref',
                'mock-commit',
                1,
                [],
                []
            ),
            [
                new Tag('tag-1', 'mock-commit'),
                new Tag('tag-2', 'mock-commit')
            ]
        );

        $this->assertEquals([], $carryforwardTags);
    }

    public function testTagToCarryforwardFromRecentCommit(): void
    {
        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(
                TagAvailabilityQuery::class,
                self::callback(
                    function (QueryParameterBag $queryParameterBag) {
                        $this->assertEquals('mock-owner', $queryParameterBag->get(QueryParameter::OWNER));
                        $this->assertEquals('mock-repository', $queryParameterBag->get(QueryParameter::REPOSITORY));
                        return true;
                    }
                )
            )
            ->willReturn(
                new TagAvailabilityCollectionQueryResult(
                    [
                        new TagAvailabilityQueryResult('tag-1', ['mock-commit']),
                        new TagAvailabilityQueryResult('tag-2', ['mock-commit-3', 'mock-commit-2']),
                    ]
                )
            );

        $carryforwardTagService = new CarryforwardTagService(
            $mockQueryService,
            new NullLogger()
        );

        $carryforwardTags = $carryforwardTagService->getTagsToCarryforward(
            new ReportWaypoint(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-ref',
                'mock-commit',
                2,
                static fn(ReportWaypoint $waypoint, int $page) => match ($page) {
                    1 => [
                        [
                            'commit' => 'mock-commit',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-2',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-3',
                            'isOnBaseRef' => false
                        ]
                    ],
                    default => [],
                },
                []
            ),
            [
                new Tag('tag-1', 'mock-current-commit')
            ]
        );

        $this->assertEquals(
            [
                new Tag('tag-2', 'mock-commit-2')
            ],
            $carryforwardTags
        );
    }

    public function testTagsToCarryforwardFromMultiplePagesOfCommits(): void
    {
        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(
                TagAvailabilityQuery::class,
                self::callback(
                    function (QueryParameterBag $queryParameterBag) {
                        $this->assertEquals('mock-owner', $queryParameterBag->get(QueryParameter::OWNER));
                        $this->assertEquals('mock-repository', $queryParameterBag->get(QueryParameter::REPOSITORY));
                        return true;
                    }
                )
            )
            ->willReturn(
                new TagAvailabilityCollectionQueryResult(
                    [
                        new TagAvailabilityQueryResult('tag-1', ['mock-commit']),
                        new TagAvailabilityQueryResult('tag-2', ['mock-commit-8', 'mock-commit-11']),
                    ]
                )
            );

        $carryforwardTagService = new CarryforwardTagService(
            $mockQueryService,
            new NullLogger()
        );

        $carryforwardTags = $carryforwardTagService->getTagsToCarryforward(
            new ReportWaypoint(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-ref',
                'mock-commit',
                3,
                static fn(ReportWaypoint $waypoint, int $page) => match ($page) {
                    1 => [
                        [
                            'commit' => 'mock-commit',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-2',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-3',
                            'isOnBaseRef' => false
                        ],
                        ...array_fill(
                            0,
                            CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 3,
                            [
                                'commit' => 'mock-commit-99',
                                'isOnBaseRef' => false
                            ]
                        )
                    ],
                    2 => [
                        [
                            'commit' => 'mock-commit-4',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-5',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-6',
                            'isOnBaseRef' => true
                        ],
                        ...array_fill(
                            0,
                            CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 3,
                            [
                                'commit' => 'mock-commit-99',
                                'isOnBaseRef' => false
                            ]
                        )
                    ],
                    3 => [
                        [
                            'commit' => 'mock-commit-7',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-8',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-9',
                            'isOnBaseRef' => true
                        ]
                    ],
                    default => [],
                },
                []
            ),
            []
        );

        $this->assertEquals(
            [
                new Tag('tag-1', 'mock-commit'),
                new Tag('tag-2', 'mock-commit-8')
            ],
            $carryforwardTags
        );
    }

    public function testTagsToCarryforwardOutOfRangeOfCommits(): void
    {
        $mockQueryService = $this->createMock(QueryService::class);
        $mockQueryService->expects($this->once())
            ->method('runQuery')
            ->with(
                TagAvailabilityQuery::class,
                self::callback(
                    function (QueryParameterBag $queryParameterBag) {
                        $this->assertEquals('mock-owner', $queryParameterBag->get(QueryParameter::OWNER));
                        $this->assertEquals('mock-repository', $queryParameterBag->get(QueryParameter::REPOSITORY));
                        return true;
                    }
                )
            )
            ->willReturn(
                new TagAvailabilityCollectionQueryResult(
                    [
                        new TagAvailabilityQueryResult('tag-1', ['mock-commit']),
                        new TagAvailabilityQueryResult('tag-2', ['mock-commit-1010', 'mock-commit-999']),
                    ]
                )
            );

        $carryforwardTagService = new CarryforwardTagService(
            $mockQueryService,
            new NullLogger()
        );

        $carryforwardTags = $carryforwardTagService->getTagsToCarryforward(
            new ReportWaypoint(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-ref',
                'mock-commit',
                null,
                static fn(ReportWaypoint $waypoint, int $page) => match ($page) {
                    1 => [
                        [
                            'commit' => 'mock-commit',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-2',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-3',
                            'isOnBaseRef' => false
                        ]
                    ],
                    2 => [
                        [
                            'commit' => 'mock-commit-4',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-5',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-6',
                            'isOnBaseRef' => true
                        ]
                    ],
                    3 => [
                        [
                            'commit' => 'mock-commit-7',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-8',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-9',
                            'isOnBaseRef' => true
                        ]
                    ],
                    4 => [
                        [
                            'commit' => 'mock-commit-10',
                            'isOnBaseRef' => true
                        ],
                        [
                            'commit' => 'mock-commit-11',
                            'isOnBaseRef' => false
                        ],
                        [
                            'commit' => 'mock-commit-12',
                            'isOnBaseRef' => true
                        ]
                    ],
                    5 => [
                        [
                            'commit' => 'mock-commit-13',
                            'isOnBaseRef' => true
                        ],
                        [
                            'commit' => 'mock-commit-14',
                            'isOnBaseRef' => true
                        ],
                        [
                            'commit' => 'mock-commit-15',
                            'isOnBaseRef' => true
                        ]
                    ],
                    default => [],
                },
                []
            ),
            []
        );

        $this->assertEquals(
            [
                new Tag('tag-1', 'mock-commit')
            ],
            $carryforwardTags
        );
    }
}
