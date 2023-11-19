<?php

namespace App\Tests\Service\History;

use App\Service\History\CommitHistoryService;
use App\Service\History\Github\GithubCommitHistoryService;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\Upload;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CommitHistoryServiceTest extends TestCase
{
    public function testGetPrecedingCommitsUsingValidProvider(): void
    {
        $event = new Upload(
            'uploadId',
            Provider::GITHUB,
            'owner',
            'repository',
            'commit',
            [],
            'ref',
            'project-root',
            12,
            new Tag('tag', 'commit'),
        );

        $mockGithubHistoryService = $this->createMock(GithubCommitHistoryService::class);
        $mockGithubHistoryService->expects($this->once())
            ->method('getPrecedingCommits')
            ->with($event, 2)
            ->willReturn([]);

        $historyService = new CommitHistoryService(
            [
                Provider::GITHUB->value => $mockGithubHistoryService,
            ]
        );

        $this->assertEquals(
            [],
            $historyService->getPrecedingCommits(
                $event,
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
            new Upload(
                'uploadId',
                Provider::GITHUB,
                'owner',
                'repository',
                'commit',
                [],
                'ref',
                'project-root',
                12,
                new Tag('tag', 'commit'),
            )
        );
    }
}
