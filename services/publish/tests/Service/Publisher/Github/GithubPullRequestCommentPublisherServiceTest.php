<?php

namespace App\Tests\Service\Publisher\Github;

use App\Enum\EnvironmentVariable;
use App\Exception\PublishingNotSupportedException;
use App\Service\Publisher\Github\GithubPullRequestCommentPublisherService;
use App\Service\Templating\TemplateRenderingService;
use App\Tests\Service\Publisher\AbstractPublisherServiceTestCase;
use Github\Api\Issue;
use Override;
use Packages\Clients\Client\Github\GithubAppInstallationClientInterface;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\Upload;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;

final class GithubPullRequestCommentPublisherServiceTest extends AbstractPublisherServiceTestCase
{
    #[Override]
    #[DataProvider('supportsDataProvider')]
    public function testSupports(EventInterface $event, bool $expectedSupport): void
    {
        $publisher = new GithubPullRequestCommentPublisherService(
            $this->createMock(GithubAppInstallationClientInterface::class),
            $this->getContainer()
                ->get(TemplateRenderingService::class),
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING
            ),
            new NullLogger()
        );

        $isSupported = $publisher->supports(
            new PublishablePullRequestMessage(
                event: $event,
                coveragePercentage: 100,
                diffCoveragePercentage: 100,
                diffUncoveredLines: 1,
                successfulUploads: 1,
                tagCoverage: [],
                leastCoveredDiffFiles: [],
                baseCommit: 'mock-base-commit',
                uncoveredLinesChange: 2,
                coverageChange: 0,
            )
        );

        $this->assertEquals($expectedSupport, $isSupported);
    }

    #[DataProvider('supportsDataProvider')]
    public function testPublishToNewComment(Upload $upload, bool $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClientInterface::class);
        $publisher = new GithubPullRequestCommentPublisherService(
            $mockGithubAppInstallationClient,
            $this->getContainer()
                ->get(TemplateRenderingService::class),
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                [
                    EnvironmentVariable::GITHUB_APP_ID->value => 'mock-github-app-id'
                ]
            ),
            new NullLogger()
        );

        if (!$expectedSupport) {
            $this->expectException(PublishingNotSupportedException::class);
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
                    'performed_via_github_app' => [
                        'id' => 'mock'
                    ]
                ],
                [
                    'id' => 2
                    // Comment wasn't performed by an app
                ]
            ]);

        $mockCommentApi->expects($expectedSupport ? $this->once() : $this->never())
            ->method('create');

        $mockCommentApi->expects($this->never())
            ->method('update');

        $publisher->publish(
            new PublishablePullRequestMessage(
                event: $upload,
                coveragePercentage: 100,
                diffCoveragePercentage: 100,
                diffUncoveredLines: 1,
                successfulUploads: 1,
                tagCoverage: [],
                leastCoveredDiffFiles: [],
                baseCommit: 'mock-base-commit',
                uncoveredLinesChange: 2,
                coverageChange: 0
            ),
        );
    }

    #[DataProvider('supportsDataProvider')]
    public function testPublishToExistingComment(Upload $upload, bool $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClientInterface::class);

        $publisher = new GithubPullRequestCommentPublisherService(
            $mockGithubAppInstallationClient,
            $this->getContainer()
                ->get(TemplateRenderingService::class),
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                [
                    EnvironmentVariable::GITHUB_APP_ID->value => 'mock-github-app-id'
                ]
            ),
            new NullLogger()
        );

        if (!$expectedSupport) {
            $this->expectException(PublishingNotSupportedException::class);
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
                    'performed_via_github_app' => [
                        'id' => 'mock-github-app-id'
                    ]
                ],
                [
                    'id' => 8,
                    // Comment wasn't performed by an app
                ],
                [
                    'id' => 2,
                    'performed_via_github_app' => [
                        'id' => 'mock-github-app-id'
                    ]
                ]
            ]);

        $mockCommentApi->expects($this->never())
            ->method('create');

        $mockCommentApi->expects($expectedSupport ? $this->once() : $this->never())
            ->method('update');

        $publisher->publish(
            new PublishablePullRequestMessage(
                event: $upload,
                coveragePercentage: 100,
                diffCoveragePercentage: 100,
                diffUncoveredLines: 1,
                successfulUploads: 1,
                tagCoverage: [],
                leastCoveredDiffFiles: [],
                baseCommit: 'mock-base-commit',
                uncoveredLinesChange: 2,
                coverageChange: 0
            ),
        );
    }

    #[Override]
    public static function supportsDataProvider(): array
    {
        return [
            [
                new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: 'mock-commit',
                    parent: ['mock-parent'],
                    ref: 'mock-ref',
                    projectRoot: 'mock-project-root',
                    tag: new Tag('mock-tag', 'mock-commit', [2]),
                    pullRequest: '1234',
                    baseCommit: 'commit-on-main',
                    baseRef: 'main',
                ),
                true
            ],
            [
                new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: 'mock-commit',
                    parent: ['mock-parent'],
                    ref: 'mock-ref',
                    projectRoot: 'mock-project-root',
                    tag: new Tag('mock-tag', 'mock-commit', [2]),
                    baseCommit: 'commit-on-main',
                    baseRef: 'main',
                ),
                false
            ]
        ];
    }
}
