<?php

namespace App\Tests\Service\History;

use App\Model\ReportWaypoint;
use App\Service\History\CachingCommitHistoryService;
use App\Service\History\CommitHistoryServiceInterface;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CachingCommitHistoryServiceTest extends TestCase
{
    public function testGetPrecedingCommitsUsesCacheForSamePage()
    {
        $mockWaypoint = $this->getMockWaypoint();

        $mockParser = $this->createMock(CommitHistoryServiceInterface::class);
        $mockParser->expects($this->once())
            ->method('getPrecedingCommits')
            ->with($mockWaypoint, 1)
            ->willReturn([
                [
                    'commit' => 'mock-commit-1',
                    'isOnBaseRef' => true
                ]
            ]);

        $cachingCommitHistoryService = new CachingCommitHistoryService(
            [
                Provider::GITHUB->value => $mockParser
            ]
        );

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-1',
                    'isOnBaseRef' => true
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
                    'isOnBaseRef' => true
                ]
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypoint, 1)
        );
    }

    public function testGetPrecedingCommitsDoesntUseCacheForDifferentPage()
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
                            'isOnBaseRef' => true
                        ]
                    ]
                ],
                [
                    $mockWaypoint,
                    2,
                    [
                        [
                            'commit' => 'mock-commit-2',
                            'isOnBaseRef' => true
                        ]
                    ]
                ]
            ]);

        $cachingCommitHistoryService = new CachingCommitHistoryService(
            [
                Provider::GITHUB->value => $mockParser
            ]
        );

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-1',
                    'isOnBaseRef' => true
                ]
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypoint, 1)
        );

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-2',
                    'isOnBaseRef' => true
                ]
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypoint, 2)
        );
    }


    public function testGetPrecedingCommitsDoesntUseCacheForDifferentWaypoints()
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
                            'isOnBaseRef' => true
                        ]
                    ]
                ],
                [
                    $mockWaypointTwo,
                    1,
                    [
                        [
                            'commit' => 'mock-commit-2',
                            'isOnBaseRef' => true
                        ]
                    ]
                ]
            ]);

        $cachingCommitHistoryService = new CachingCommitHistoryService(
            [
                Provider::GITHUB->value => $mockParser
            ]
        );

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-1',
                    'isOnBaseRef' => true
                ]
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypointOne, 1)
        );

        $this->assertEquals(
            [
                [
                    'commit' => 'mock-commit-2',
                    'isOnBaseRef' => true
                ]
            ],
            $cachingCommitHistoryService->getPrecedingCommits($mockWaypointTwo, 1)
        );
    }

    private function getMockWaypoint(): ReportWaypoint|MockObject
    {
        $mockWaypoint = $this->createMock(ReportWaypoint::class);
        $mockWaypoint->method('getProvider')
            ->willReturn(Provider::GITHUB);

        return $mockWaypoint;
    }
}
