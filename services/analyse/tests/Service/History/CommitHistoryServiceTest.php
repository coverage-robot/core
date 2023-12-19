<?php

namespace App\Tests\Service\History;

use App\Model\ReportWaypoint;
use App\Service\History\CommitHistoryService;
use App\Service\History\Github\GithubCommitHistoryService;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CommitHistoryServiceTest extends TestCase
{
    public function testGetPrecedingCommitsUsingValidProvider(): void
    {
        $waypoint = new ReportWaypoint(
            Provider::GITHUB,
            'owner',
            'repository',
            'ref',
            'commit',
            null,
            [],
            []
        );

        $mockGithubHistoryService = $this->createMock(GithubCommitHistoryService::class);
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
        $mockGithubHistoryService = $this->createMock(GithubCommitHistoryService::class);
        $mockGithubHistoryService->expects($this->never())
            ->method('getPrecedingCommits');

        $historyService = new CommitHistoryService(
            [
                'a-different-provider' => $mockGithubHistoryService,
            ]
        );

        $this->expectException(RuntimeException::class);

        $historyService->getPrecedingCommits(
            new ReportWaypoint(
                Provider::GITHUB,
                'owner',
                'repository',
                'ref',
                'commit',
                null,
                [],
                []
            )
        );
    }
}
