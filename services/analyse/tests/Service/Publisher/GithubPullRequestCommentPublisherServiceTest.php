<?php

namespace App\Tests\Service\Publisher;

use App\Client\Github\GithubAppClient;
use App\Client\Github\GithubAppInstallationClient;
use App\Enum\ProviderEnum;
use App\Exception\PublishException;
use App\Model\PublishableCoverageDataInterface;
use App\Model\Upload;
use App\Service\Formatter\PullRequestCommentFormatterService;
use App\Service\Publisher\GithubPullRequestCommentPublisherService;
use Github\Api\Issue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GithubPullRequestCommentPublisherServiceTest extends TestCase
{
    #[DataProvider('supportsDataProvider')]
    public function testSupports(Upload $upload, bool $expectedSupport)
    {
        $publisher = new GithubPullRequestCommentPublisherService(
            $this->createMock(GithubAppInstallationClient::class),
            new PullRequestCommentFormatterService(),
            new NullLogger()
        );

        $isSupported = $publisher->supports($upload, $this->createMock(PublishableCoverageDataInterface::class));

        $this->assertEquals($expectedSupport, $isSupported);
    }

    #[DataProvider('supportsDataProvider')]
    public function testPublishToNewComment($upload, $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $publisher = new GithubPullRequestCommentPublisherService(
            $mockGithubAppInstallationClient,
            new PullRequestCommentFormatterService(),
            new NullLogger()
        );

        if (!$expectedSupport) {
            $this->expectExceptionObject(PublishException::notSupportedException());
        }

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);

        $mockGithubAppInstallationClient->expects($expectedSupport ? $this->once() : $this->never())
            ->method('authenticateAsRepositoryOwner')
            ->with($upload->getOwner());

        $mockIssueApi = $this->createMock(Issue::class);
        $mockCommentApi = $this->createMock(Issue\Comments::class);

        $mockGithubAppInstallationClient->expects($expectedSupport ? $this->exactly(2) : $this->never())
            ->method('issue')
            ->willReturn($mockIssueApi);

        $mockIssueApi->expects($expectedSupport ? $this->exactly(2) : $this->never())
            ->method('comments')
            ->willReturn($mockCommentApi);

        $mockCommentApi->expects($expectedSupport ? $this->once() : $this->never())
            ->method('all')
            ->with($upload->getOwner(), $upload->getRepository(), $upload->getPullRequest())
            ->willReturn([
                [
                    'id' => 1,
                    'user' => [
                        'node_id' => 'node-1'
                    ]
                ],
                [
                    'id' => 2,
                    'user' => [
                        'node_id' => 'node-2'
                    ]
                ]
            ]);

        $mockCommentApi->expects($expectedSupport ? $this->once() : $this->never())
            ->method('create');

        $mockCommentApi->expects($this->never())
            ->method('update');

        $publisher->publish($upload, $mockPublishableCoverageData);
    }

    #[DataProvider('supportsDataProvider')]
    public function testPublishToExistingComment(Upload $upload, bool $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $publisher = new GithubPullRequestCommentPublisherService(
            $mockGithubAppInstallationClient,
            new PullRequestCommentFormatterService(),
            new NullLogger()
        );

        if (!$expectedSupport) {
            $this->expectExceptionObject(PublishException::notSupportedException());
        }

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);

        $mockGithubAppInstallationClient->expects($expectedSupport ? $this->once() : $this->never())
            ->method('authenticateAsRepositoryOwner')
            ->with($upload->getOwner());

        $mockIssueApi = $this->createMock(Issue::class);
        $mockCommentApi = $this->createMock(Issue\Comments::class);

        $mockGithubAppInstallationClient->expects($expectedSupport ? $this->exactly(2) : $this->never())
            ->method('issue')
            ->willReturn($mockIssueApi);

        $mockIssueApi->expects($expectedSupport ? $this->exactly(2) : $this->never())
            ->method('comments')
            ->willReturn($mockCommentApi);

        $mockCommentApi->expects($expectedSupport ? $this->once() : $this->never())
            ->method('all')
            ->with(
                $upload->getOwner(),
                $upload->getRepository(),
                $upload->getPullRequest()
            )
            ->willReturn([
                [
                    'id' => 1,
                    'user' => [
                        'node_id' => 'node-1'
                    ]
                ],
                [
                    'id' => 2,
                    'user' => [
                        'node_id' => GithubAppClient::BOT_ID
                    ]
                ]
            ]);

        $mockCommentApi->expects($this->never())
            ->method('create');

        $mockCommentApi->expects($expectedSupport ? $this->once() : $this->never())
            ->method('update');

        $publisher->publish($upload, $mockPublishableCoverageData);
    }

    public static function supportsDataProvider(): array
    {
        return [
            [
                new Upload(
                    [
                        'uploadId' => 'mock-uuid',
                        'provider' => ProviderEnum::GITHUB->value,
                        'owner' => 'mock-owner',
                        'repository' => 'mock-repository',
                        'commit' => 'mock-commit',
                        'parent' => 'mock-parent',
                        'ref' => 'mock-ref',
                        'pullRequest' => '1234'
                    ]
                ),
                true
            ],
            [
                new Upload(
                    [
                        'uploadId' => 'mock-uuid',
                        'provider' => ProviderEnum::GITHUB->value,
                        'owner' => 'mock-owner',
                        'repository' => 'mock-repository',
                        'commit' => 'mock-commit',
                        'parent' => 'mock-parent',
                        'ref' => 'mock-ref',
                    ]
                ),
                false
            ]
        ];
    }
}
