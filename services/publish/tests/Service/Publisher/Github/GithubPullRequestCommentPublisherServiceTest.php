<?php

namespace App\Tests\Service\Publisher\Github;

use App\Enum\EnvironmentVariable;
use App\Exception\PublishException;
use App\Service\Formatter\PullRequestCommentFormatterService;
use App\Service\Publisher\Github\GithubPullRequestCommentPublisherService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use DateTimeImmutable;
use Github\Api\Issue;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\Upload;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Packages\Models\Model\Tag;
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
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING
            ),
            new NullLogger()
        );

        $isSupported = $publisher->supports(
            new PublishablePullRequestMessage(
                $upload,
                100,
                0,
                100,
                1,
                [],
                [],
                new DateTimeImmutable()
            )
        );

        $this->assertEquals($expectedSupport, $isSupported);
    }

    #[DataProvider('supportsDataProvider')]
    public function testPublishToNewComment($upload, $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $publisher = new GithubPullRequestCommentPublisherService(
            $mockGithubAppInstallationClient,
            new PullRequestCommentFormatterService(),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::GITHUB_BOT_ID->value => 'mock-github-bot-id'
                ]
            ),
            new NullLogger()
        );

        if (!$expectedSupport) {
            $this->expectExceptionObject(PublishException::notSupportedException());
        }

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

        $publisher->publish(
            new PublishablePullRequestMessage(
                $upload,
                100,
                0,
                100,
                1,
                [],
                [],
                new DateTimeImmutable()
            ),
        );
    }

    #[DataProvider('supportsDataProvider')]
    public function testPublishToExistingComment(Upload $upload, bool $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $publisher = new GithubPullRequestCommentPublisherService(
            $mockGithubAppInstallationClient,
            new PullRequestCommentFormatterService(),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::GITHUB_BOT_ID->value => 'mock-github-bot-id'
                ]
            ),
            new NullLogger()
        );

        if (!$expectedSupport) {
            $this->expectExceptionObject(PublishException::notSupportedException());
        }

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
                        'node_id' => 'mock-github-bot-id'
                    ]
                ]
            ]);

        $mockCommentApi->expects($this->never())
            ->method('create');

        $mockCommentApi->expects($expectedSupport ? $this->once() : $this->never())
            ->method('update');

        $publisher->publish(
            new PublishablePullRequestMessage(
                $upload,
                100,
                0,
                100,
                1,
                [],
                [],
                new DateTimeImmutable()
            ),
        );
    }

    public static function supportsDataProvider(): array
    {
        return [
            [
                new Upload(
                    'mock-uuid',
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repository',
                    'mock-commit',
                    ['mock-parent'],
                    'mock-ref',
                    'mock-project-root',
                    '1234',
                    'commit-on-main',
                    'main',
                    new Tag('mock-tag', 'mock-commit'),
                ),
                true
            ],
            [
                new Upload(
                    'mock-uuid',
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repository',
                    'mock-commit',
                    ['mock-parent'],
                    'mock-ref',
                    'mock-project-root',
                    null,
                    'commit-on-main',
                    'main',
                    new Tag('mock-tag', 'mock-commit'),
                ),
                false
            ]
        ];
    }
}
