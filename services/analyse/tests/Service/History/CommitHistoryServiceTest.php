<?php

namespace App\Tests\Service\History;

use App\Service\History\CommitHistoryService;
use App\Service\History\Github\GithubCommitHistoryService;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CommitHistoryServiceTest extends TestCase
{
    public function testGetPrecedingCommitsUsingValidProvider(): void
    {
        $mockGithubHistoryService = $this->createMock(GithubCommitHistoryService::class);
        $mockGithubHistoryService->expects($this->once())
            ->method('getPrecedingCommits')
            ->willReturn([]);

        $historyService = new CommitHistoryService(
            [
                Provider::GITHUB->value => $mockGithubHistoryService,
            ]
        );

        $this->assertEquals(
            [],
            $historyService->getPrecedingCommits(
                Upload::from([
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'owner',
                    'repository' => 'repository',
                    'commit' => 'commit',
                    'uploadId' => 'uploadId',
                    'ref' => 'ref',
                    'parent' => [],
                    'tag' => 'tag',
                ])
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
            Upload::from([
                'provider' => Provider::GITHUB->value,
                'owner' => 'owner',
                'repository' => 'repository',
                'commit' => 'commit',
                'uploadId' => 'uploadId',
                'ref' => 'ref',
                'parent' => [],
                'tag' => 'tag',
            ])
        );
    }
}
