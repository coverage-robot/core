<?php

namespace App\Tests\Service\Publisher\Github;

use App\Enum\EnvironmentVariable;
use App\Service\Publisher\Github\GithubReviewPublisherService;
use App\Service\Templating\TemplateRenderingService;
use App\Tests\Service\Publisher\AbstractPublisherServiceTestCase;
use Github\Api\GraphQL;
use Github\Api\PullRequest;
use Override;
use Packages\Clients\Client\Github\GithubAppInstallationClientInterface;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Configuration\Mock\MockSettingServiceFactory;
use Packages\Configuration\Model\LineCommentType;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\Upload;
use Packages\Message\PublishableMessage\PublishableLineCommentMessageCollection;
use Packages\Message\PublishableMessage\PublishablePartialBranchLineCommentMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response;

final class GithubReviewPublisherServiceTest extends AbstractPublisherServiceTestCase
{
    #[Override]
    #[DataProvider('supportsDataProvider')]
    public function testSupports(EventInterface $event, bool $expectedSupport): void
    {
        $publisher = new GithubReviewPublisherService(
            $this->getContainer()
                ->get(TemplateRenderingService::class),
            MockSettingServiceFactory::createMock(
                [
                    SettingKey::LINE_COMMENT_TYPE->value => LineCommentType::REVIEW_COMMENT
                ]
            ),
            $this->createMock(GithubAppInstallationClientInterface::class),
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING
            ),
            new NullLogger()
        );

        $this->assertEquals(
            $expectedSupport,
            $publisher->supports(
                new PublishableLineCommentMessageCollection(
                    event: $event,
                    messages: []
                )
            )
        );
    }

    public function testPublishingNewReviewWhenCommitIsNotHead(): void
    {
        $event = new Upload(
            uploadId: 'mock-uuid',
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            commit: 'mock-commit',
            parent: ['mock-parent'],
            ref: 'mock-ref',
            projectRoot: 'mock-project-root',
            tag: new Tag('mock-tag', 'mock-commit', [0]),
            pullRequest: '1234',
            baseCommit: 'mock-base-commit',
            baseRef: 'main',
        );

        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClientInterface::class);

        $publisher = new GithubReviewPublisherService(
            $this->getContainer()
                ->get(TemplateRenderingService::class),
            MockSettingServiceFactory::createMock(
                [
                    SettingKey::LINE_COMMENT_TYPE->value => LineCommentType::REVIEW_COMMENT
                ]
            ),
            $mockGithubAppInstallationClient,
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING
            ),
            new NullLogger()
        );

        $mockGithubAppInstallationClient->expects($this->once())
            ->method('authenticateAsRepositoryOwner')
            ->with($event->getOwner());

        $mockPullRequestApi = $this->createMock(PullRequest::class);
        $mockPullRequestApi->expects($this->once())
            ->method('show')
            ->willReturn([
                'head' => [
                    'sha' => 'not-the-current-commit'
                ]
            ]);
        $mockPullRequestApi->expects($this->never())
            ->method('comments');
        $mockPullRequestApi->expects($this->never())
            ->method('reviews');

        $mockGithubAppInstallationClient->expects($this->once())
            ->method('pullRequest')
            ->willReturn($mockPullRequestApi);

        $mockGithubAppInstallationClient->method('getLastResponse')
            ->willReturn(new \Nyholm\Psr7\Response(Response::HTTP_OK));

        $publisher->publish(
            new PublishableLineCommentMessageCollection(
                event: $event,
                messages: []
            )
        );
    }

    public function testPublishingNewReviewOnPullRequestHead(): void
    {
        $event = new Upload(
            uploadId: 'mock-uuid',
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            commit: 'mock-commit',
            parent: ['mock-parent'],
            ref: 'mock-ref',
            projectRoot: 'mock-project-root',
            tag: new Tag('mock-tag', 'mock-commit', [0]),
            pullRequest: '1234',
            baseCommit: 'mock-base-commit',
            baseRef: 'main',
        );

        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClientInterface::class);

        $publisher = new GithubReviewPublisherService(
            $this->getContainer()
                ->get(TemplateRenderingService::class),
            MockSettingServiceFactory::createMock(
                [
                    SettingKey::LINE_COMMENT_TYPE->value => LineCommentType::REVIEW_COMMENT
                ]
            ),
            $mockGithubAppInstallationClient,
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                [
                    EnvironmentVariable::GITHUB_APP_ID->value => 'mock-github-app-id'
                ]
            ),
            new NullLogger()
        );

        $mockGithubAppInstallationClient->expects($this->once())
            ->method('authenticateAsRepositoryOwner')
            ->with($event->getOwner());

        $mockReviewApi = $this->createMock(PullRequest\Review::class);
        $mockReviewApi->expects($this->once())
            ->method('create')
            ->with(
                $event->getOwner(),
                $event->getRepository(),
                $event->getPullRequest(),
                [
                    'commit_id' => $event->getCommit(),
                    'body' => '',
                    'event' => 'COMMENT',
                    'comments' => [
                        [
                            'path' => 'mock-file',
                            'line' => 1,
                            'side' => 'RIGHT',
                            'body' => '50% of these branches are not covered by any tests.'
                        ]
                    ]
                ]
            )
            ->willReturn([
                'id' => 1
            ]);

        $mockPullRequestApi = $this->createMock(PullRequest::class);
        $mockPullRequestApi->expects($this->once())
            ->method('show')
            ->willReturn([
                'head' => [
                    'sha' => $event->getCommit()
                ]
            ]);
        $mockPullRequestApi->expects($this->once())
            ->method('reviews')
            ->willReturn($mockReviewApi);
        $mockGithubAppInstallationClient->method('pullRequest')
            ->willReturn($mockPullRequestApi);

        $mockGraphApi = $this->createMock(GraphQL::class);
        $mockGraphApi->expects($this->once())
            ->method('execute')
            ->willReturn([]);
        $mockGithubAppInstallationClient->method('graphql')
            ->willReturn($mockGraphApi);

        $mockGithubAppInstallationClient->method('getLastResponse')
            ->willReturn(new \Nyholm\Psr7\Response(Response::HTTP_OK));

        $publisher->publish(
            publishableMessage: new PublishableLineCommentMessageCollection(
                event: $event,
                messages: [
                    new PublishablePartialBranchLineCommentMessage(
                        event: $event,
                        fileName: 'mock-file',
                        startLineNumber: 1,
                        endLineNumber: 2,
                        totalBranches: 2,
                        coveredBranches: 1
                    )
                ]
            )
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
                    baseCommit: 'mock-base-commit'
                ),
                false
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
                    pullRequest: '1234',
                    baseCommit: 'mock-base-commit',
                    baseRef: 'main',
                ),
                true
            ]
        ];
    }
}
