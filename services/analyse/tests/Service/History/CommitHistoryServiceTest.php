<?php

declare(strict_types=1);

namespace App\Tests\Service\History;

use App\Exception\CommitHistoryException;
use App\Model\ReportWaypoint;
use App\Service\History\CommitHistoryService;
use App\Service\History\CommitHistoryServiceInterface;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;

final class CommitHistoryServiceTest extends TestCase
{
    public function testGetPrecedingCommitsUsingValidProvider(): void
    {
        $waypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: 'mock-project',
            owner: 'owner',
            repository: 'repository',
            ref: 'ref',
            commit: 'commit',
            history: [],
            diff: []
        );

        $mockGithubHistoryService = $this->createMock(CommitHistoryServiceInterface::class);
        $mockGithubHistoryService->expects($this->once())
            ->method('getPrecedingCommits')
            ->with($waypoint, 2)
            ->willReturn([]);

        $historyService = new CommitHistoryService(
            [
                Provider::GITHUB->value => $mockGithubHistoryService,
            ]
        );

        $this->assertEquals(
            [],
            $historyService->getPrecedingCommits(
                $waypoint,
                2
            )
        );
    }

    public function testGetPrecedingCommitsUsingInvalidProvider(): void
    {
        $mockGithubHistoryService = $this->createMock(CommitHistoryServiceInterface::class);
        $mockGithubHistoryService->expects($this->never())
            ->method('getPrecedingCommits');

        $historyService = new CommitHistoryService(
            [
                'a-different-provider' => $mockGithubHistoryService,
            ]
        );

        $this->expectException(CommitHistoryException::class);

        $historyService->getPrecedingCommits(
            new ReportWaypoint(
                provider: Provider::GITHUB,
                projectId: 'mock-project',
                owner: 'owner',
                repository: 'repository',
                ref: 'ref',
                commit: 'commit',
                history: [],
                diff: []
            )
        );
    }
}
