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
                provider: Provider::GITHUB,
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: [],
                diff: []
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
                provider: Provider::GITHUB,
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: static fn(ReportWaypoint $waypoint, int $page) => match ($page) {
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
                provider: Provider::GITHUB,
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                pullRequest: 3,
                history: static fn(ReportWaypoint $waypoint, int $page) => match ($page) {
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
                diff: []
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
                provider: Provider::GITHUB,
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: static fn(ReportWaypoint $waypoint, int $page) => match ($page) {
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
                        ]
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
                        ]
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
                        ]
                    ],
                    default => [],
                },
                diff: []
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
