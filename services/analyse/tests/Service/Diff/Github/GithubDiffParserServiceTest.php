<?php

namespace App\Tests\Service\Diff\Github;

use App\Service\Diff\Github\GithubDiffParserService;
use Github\Api\PullRequest;
use Github\Api\Repo;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Event\Model\Upload;
use Packages\Models\Enum\Provider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SebastianBergmann\Diff\Parser;

class GithubDiffParserServiceTest extends TestCase
{
    public function testGetDiffFromPullRequest(): void
    {
        $mockApiClient = $this->createMock(GithubAppInstallationClient::class);
        $mockPullRequestApi = $this->createMock(PullRequest::class);

        $parser = new GithubDiffParserService(
            $mockApiClient,
            new Parser(),
            new NullLogger()
        );

        $mockUpload = $this->getMockUpload();

        $mockUpload->expects($this->exactly(4))
            ->method('getPullRequest')
            ->willReturn(1);
        $mockUpload->expects($this->exactly(2))
            ->method('getCommit');

        $mockApiClient->expects($this->once())
            ->method('authenticateAsRepositoryOwner')
            ->with('mock-owner');
        $mockApiClient->expects($this->once())
            ->method('pullRequest')
            ->willReturn($mockPullRequestApi);
        $mockPullRequestApi->expects($this->once())
            ->method('configure')
            ->willReturn($mockPullRequestApi);
        $mockPullRequestApi->expects($this->once())
            ->method('show')
            ->willReturn(
                <<<DIFF
                --- a/file-1.php
                +++ b/file-1.php
                @@ -162,7 +162,7 @@
                                     line-1
                                 line-2

                -            line-3.
                +            line-3
                                 line-4
                                     line-5
                                         line-6
                --- a/file-2.php
                +++ b/file-2.php
                @@ -162,7 +162,7 @@
                                     line-1
                                 line-2

                -            line-3
                +            line-3.
                                 line-4
                                     line-5
                                         line-6
                DIFF
            );

        $addedLines = $parser->get($mockUpload);

        $this->assertEquals(
            [
                'file-1.php' => [165],
                'file-2.php' => [165],
            ],
            $addedLines
        );
    }

    public function testGetDiffFromCommit(): void
    {
        $mockApiClient = $this->createMock(GithubAppInstallationClient::class);
        $mockRepoApi = $this->createMock(Repo::class);

        $parser = new GithubDiffParserService(
            $mockApiClient,
            new Parser(),
            new NullLogger()
        );

        $mockUpload = $this->getMockUpload();

        $mockUpload->expects($this->exactly(3))
            ->method('getPullRequest')
            ->willReturn(null);
        $mockUpload->expects($this->atLeast(1))
            ->method('getCommit');

        $mockApiClient->expects($this->once())
            ->method('authenticateAsRepositoryOwner')
            ->with('mock-owner');
        $mockApiClient->expects($this->once())
            ->method('repo')
            ->willReturn($mockRepoApi);
        $mockRepoApi->expects($this->once())
            ->method('commits')
            ->willReturn($mockRepoApi);
        $mockRepoApi->expects($this->once())
            ->method('show')
            ->willReturn([
                'files' => [
                    [
                        'filename' => 'file-1.php',
                        'patch' => <<<DIFF
                        @@ -170,7 +170,7 @@
                                             line-1
                                         line-2

                        -            line-3.
                        +            line-3
                                         line-4
                                             line-5
                                                 line-6
                        DIFF
                    ],
                    [
                        'filename' => 'file-2.php',
                        'patch' => <<<DIFF
                        @@ -170,7 +170,7 @@
                                     line-1
                                 line-2

                        -            line-3
                        +            line-3.
                                         line-4
                                             line-5
                                                 line-6
                        DIFF
                    ],
                    [
                        'filename' => 'file-3.php',
                        'patch' => <<<DIFF
                        @@ -170,7 +170,7 @@
                                     line-1
                                 line-2

                        -            line-3
                        +            line-3.
                                         line-4
                                             line-5
                                                 line-6
                        @@ -180,5 +182,7 @@
                                     line-1
                                 line-2

                        -            line-3
                        +            line-3.
                        +            line-4.
                        +            line-5.
                                         line-6
                                             line-7
                                                 line-8
                        DIFF
                    ]
                ]
            ]);

        $addedLines = $parser->get($mockUpload);

        $this->assertEquals(
            [
                'file-1.php' => [173],
                'file-2.php' => [173],
                'file-3.php' => [173, 185, 186, 187],
            ],
            $addedLines
        );
    }

    public function testGetProvider(): void
    {
        $parser = new GithubDiffParserService(
            $this->createMock(GithubAppInstallationClient::class),
            new Parser(),
            new NullLogger()
        );

        $this->assertEquals(
            Provider::GITHUB->value,
            $parser->getProvider()
        );
    }

    private function getMockUpload(): Upload|MockObject
    {
        $mockUpload = $this->createMock(Upload::class);
        $mockUpload->expects($this->exactly(4))
            ->method('getOwner')
            ->willReturn('mock-owner');
        $mockUpload->expects($this->exactly(3))
            ->method('getRepository')
            ->willReturn('mock-repository');

        return $mockUpload;
    }
}
