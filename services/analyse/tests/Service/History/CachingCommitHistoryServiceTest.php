<?php

namespace App\Tests\Service\History;

use App\Model\ReportWaypoint;
use App\Service\History\CachingCommitHistoryService;
use App\Service\History\CommitHistoryService;
use App\Service\History\CommitHistoryServiceInterface;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CachingCommitHistoryServiceTest extends TestCase
{
    public function testGetPrecedingCommitsUsesCacheForSamePage(): void
    {
        $mockWaypoint = $this->getMockWaypoint();

        $mockParser = $this->createMock(CommitHistoryServiceInterface::class);
        $mockParser->expects($this->once())
            ->method('getPrecedingCommits')
            ->with($mockWaypoint, 1)
            ->willReturn([
                [
                    'commit' => 'mock-commit-1',
                    'ref' => 'main-branch',
                    'merged' => true
                ]
            ]);

        $cachingCommitHistoryService = new CachingCommitHistoryService(
            new CommitHistoryService(
                [
                    Provider::GITHUB->value => $mockParser
                ]
            ),
            new NullLogger()
        );

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-1',
                    'ref' => 'main-branch',
                    'merged' => true
                ]
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypoint, 1)
        );

        // We use the cache rather than calling the parser again
        $mockParser->expects($this->never())
            ->method('getPrecedingCommits');

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-1',
                    'ref' => 'main-branch',
                    'merged' => true
                ]
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypoint, 1)
        );
    }

    public function testGetPrecedingCommitsDoesntUseCacheForDifferentPage(): void
    {
        $mockWaypoint = $this->getMockWaypoint();

        $mockParser = $this->createMock(CommitHistoryServiceInterface::class);
        $mockParser->expects($this->exactly(2))
            ->method('getPrecedingCommits')
            ->willReturnMap([
                [
                    $mockWaypoint,
                    1,
                    [
                        [
                            'commit' => 'mock-commit-1',
                            'ref' => 'main-branch',
                            'merged' => true
                        ]
                    ]
                ],
                [
                    $mockWaypoint,
                    2,
                    [
                        [
                            'commit' => 'mock-commit-2',
                            'ref' => 'main-branch',
                            'merged' => true
                        ]
                    ]
                ]
            ]);

        $cachingCommitHistoryService = new CachingCommitHistoryService(
            new CommitHistoryService(
                [
                    Provider::GITHUB->value => $mockParser
                ]
            ),
            new NullLogger()
        );

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-1',
                    'ref' => 'main-branch',
                    'merged' => true
                ]
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypoint, 1)
        );

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-2',
                    'ref' => 'main-branch',
                    'merged' => true
                ]
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypoint, 2)
        );
    }

    public function testGetPrecedingCommitsDoesntUseCacheForDifferentWaypoints(): void
    {
        $mockWaypointOne = $this->getMockWaypoint();
        $mockWaypointTwo = $this->getMockWaypoint();

        $mockParser = $this->createMock(CommitHistoryServiceInterface::class);
        $mockParser->expects($this->exactly(2))
            ->method('getPrecedingCommits')
            ->willReturnMap([
                [
                    $mockWaypointOne,
                    1,
                    [
                        [
                            'commit' => 'mock-commit-1',
                            'ref' => 'main-branch',
                            'merged' => true
                        ]
                    ]
                ],
                [
                    $mockWaypointTwo,
                    1,
                    [
                        [
                            'commit' => 'mock-commit-2',
                            'ref' => 'main-branch',
                            'merged' => true
                        ]
                    ]
                ]
            ]);

        $cachingCommitHistoryService = new CachingCommitHistoryService(
            new CommitHistoryService(
                [
                    Provider::GITHUB->value => $mockParser
                ]
            ),
            new NullLogger()
        );

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-1',
                    'ref' => 'main-branch',
                    'merged' => true
                ]
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypointOne, 1)
        );

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-2',
                    'ref' => 'main-branch',
                    'merged' => true
                ]
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypointTwo, 1)
        );
    }

    public function testGetPrecedingCommitsCanUseComparableCommitsAsOverlappingCache(): void
    {
        $mockWaypointOne = $this->getMockWaypoint('mock-commit-1');
        $mockWaypointTwo = $this->getMockWaypoint('mock-commit-3');

        $mockParser = $this->createMock(CommitHistoryServiceInterface::class);
        $mockParser->expects($this->exactly(2))
            ->method('getPrecedingCommits')
            ->willReturnMap([
                [
                    $mockWaypointOne,
                    1,
                    [
                        [
                            'commit' => 'mock-commit-1',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        [
                            'commit' => 'mock-commit-2',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        [
                            'commit' => 'mock-commit-3',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        [
                            'commit' => 'mock-commit-4',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        ...array_fill(
                            0,
                            CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 4,
                            [
                                'commit' => 'mock-commit-5',
                                'ref' => 'main-branch',
                                'merged' => true
                            ]
                        )
                    ],
                ],
                [
                    $mockWaypointOne,
                    2,
                    [
                        [
                            'commit' => 'mock-commit-6',
                            'ref' => 'main-branch',
                            'merged' => true
                        ]
                    ]
                ]
            ]);

        $cachingCommitHistoryService = new CachingCommitHistoryService(
            new CommitHistoryService(
                [
                    Provider::GITHUB->value => $mockParser
                ]
            ),
            new NullLogger()
        );

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-1',
                    'ref' => 'main-branch',
                    'merged' => true
                ],
                [
                    'commit' => 'mock-commit-2',
                    'ref' => 'main-branch',
                    'merged' => true
                ],
                [
                    'commit' => 'mock-commit-3',
                    'ref' => 'main-branch',
                    'merged' => true
                ],
                [
                    'commit' => 'mock-commit-4',
                    'ref' => 'main-branch',
                    'merged' => true
                ],
                ...array_fill(
                    0,
                    CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 4,
                    [
                        'commit' => 'mock-commit-5',
                        'ref' => 'main-branch',
                        'merged' => true
                    ]
                ),
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypointOne)
        );

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-4',
                    'ref' => 'main-branch',
                    'merged' => true
                ],
                ...array_fill(
                    0,
                    CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 4,
                    [
                        'commit' => 'mock-commit-5',
                        'ref' => 'main-branch',
                        'merged' => true
                    ]
                ),
                [
                    'commit' => 'mock-commit-6',
                    'ref' => 'main-branch',
                    'merged' => true
                ]
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypointTwo, 1)
        );
    }

    public function testGetPrecedingCommitsCanUseComparableCommitsFromDifferentPagesAsOverlappingCache(): void
    {
        $mockWaypointOne = $this->getMockWaypoint();
        $mockWaypointTwo = $this->getMockWaypoint();

        $mockParser = $this->createMock(CommitHistoryServiceInterface::class);
        $mockParser->expects($this->exactly(2))
            ->method('getPrecedingCommits')
            ->willReturnMap([
                [
                    $mockWaypointOne,
                    1,
                    [
                        [
                            'commit' => 'mock-commit-1',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        [
                            'commit' => 'mock-commit-2',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        [
                            'commit' => 'mock-commit-3',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        [
                            'commit' => 'mock-commit-4',
                            'ref' => 'main-branch',
                            'merged' => true
                        ],
                        ...array_fill(
                            0,
                            CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 4,
                            [
                                'commit' => 'mock-commit-5',
                                'ref' => 'main-branch',
                                'merged' => true
                            ]
                        )
                    ],
                ],
                [
                    $mockWaypointTwo,
                    2,
                    array_fill(
                        0,
                        CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 3,
                        [
                            'commit' => 'mock-commit-6',
                            'ref' => 'main-branch',
                            'merged' => true
                        ]
                    )
                ],
            ]);

        $cachingCommitHistoryService = new CachingCommitHistoryService(
            new CommitHistoryService(
                [
                    Provider::GITHUB->value => $mockParser
                ]
            ),
            new NullLogger()
        );

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-1',
                    'ref' => 'main-branch',
                    'merged' => true
                ],
                [
                    'commit' => 'mock-commit-2',
                    'ref' => 'main-branch',
                    'merged' => true
                ],
                [
                    'commit' => 'mock-commit-3',
                    'ref' => 'main-branch',
                    'merged' => true
                ],
                [
                    'commit' => 'mock-commit-4',
                    'ref' => 'main-branch',
                    'merged' => true
                ],
                ...array_fill(
                    0,
                    CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 4,
                    [
                        'commit' => 'mock-commit-5',
                        'ref' => 'main-branch',
                        'merged' => true
                    ]
                ),
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypointOne, 1)
        );

        $this->assertEquals(
            [
                ...array_fill(
                    0,
                    CommitHistoryService::COMMITS_TO_RETURN_PER_PAGE - 3,
                    [
                        'commit' => 'mock-commit-6',
                        'ref' => 'main-branch',
                        'merged' => true
                    ]
                )
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypointTwo, 2)
        );
    }

    private function getMockWaypoint(string $commit = 'mock-commit'): ReportWaypoint|MockObject
    {
        return new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: $commit,
            history: [],
            diff: [],
            pullRequest: 1
        );
    }
}
