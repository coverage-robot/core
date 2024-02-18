<?php

namespace App\Tests\Service\Publisher\Github;

use App\Enum\EnvironmentVariable;
use App\Service\Publisher\Github\GithubAnnotationPublisherService;
use App\Service\Templating\TemplateRenderingService;
use App\Tests\Service\Publisher\AbstractPublisherServiceTestCase;
use Github\Api\Repo;
use Github\Api\Repository\Checks\CheckRuns;
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

final class GithubAnnotationPublisherServiceTest extends AbstractPublisherServiceTestCase
{
    #[Override]
    #[DataProvider('supportsDataProvider')]
    public function testSupports(EventInterface $event, bool $expectedSupport): void
    {
        $publisher = new GithubAnnotationPublisherService(
            $this->getContainer()
                ->get(TemplateRenderingService::class),
            MockSettingServiceFactory::createMock(
                $this,
                [
                    SettingKey::LINE_COMMENT_TYPE->value => LineCommentType::ANNOTATION
                ]
            ),
            $this->createMock(GithubAppInstallationClientInterface::class),
            MockEnvironmentServiceFactory::createMock(
                $this,
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

    public function testPublishingAnnotationsOnCommit(): void
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
            tag: new Tag('mock-tag', 'mock-commit'),
            pullRequest: '1234',
            baseCommit: 'mock-base-commit',
            baseRef: 'main',
        );

        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClientInterface::class);

        $publisher = new GithubAnnotationPublisherService(
            $this->getContainer()
                ->get(TemplateRenderingService::class),
            MockSettingServiceFactory::createMock(
                $this,
                [
                    SettingKey::LINE_COMMENT_TYPE->value => LineCommentType::ANNOTATION
                ]
            ),
            $mockGithubAppInstallationClient,
            MockEnvironmentServiceFactory::createMock(
                $this,
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

        $mockCheckRunsApi = $this->createMock(CheckRuns::class);
        $mockRepoApi = $this->createMock(Repo::class);

        $mockCheckRunsApi->expects($this->once())
            ->method('allForReference')
            ->willReturn([
                'check_runs' => [
                    [
                        'id' => 2,
                        'conclusion' => 'success',
                        'output' => [
                            'summary' => 'mock-summary',
                            'title' => 'mock-title'
                        ],
                        'app' => [
                            'id' => 'mock-github-app-id'
                        ]
                    ]
                ]
            ]);
        $mockCheckRunsApi->expects($this->once())
            ->method('update')
            ->with(
                $event->getOwner(),
                $event->getRepository(),
                2,
                [
                    'output' => [
                        'title' => 'mock-title',
                        'summary' => 'mock-summary',
                        'annotations' => [
                            [
                                'path' => 'mock-file',
                                'annotation_level' => 'warning',
                                'title' => 'Opportunity For New Coverage',
                                'message' => '50% of these branches are not covered by any tests.',
                                'start_line' => 1,
                                'end_line' => 1
                            ]
                        ],
                    ]
                ]
            );

        $mockRepoApi->method('checkRuns')
            ->willReturn($mockCheckRunsApi);

        $mockGithubAppInstallationClient->method('repo')
            ->willReturn($mockRepoApi);

        $mockGithubAppInstallationClient->method('getLastResponse')
            ->willReturn(new \Nyholm\Psr7\Response(Response::HTTP_OK));

        $this->assertTrue(
            $publisher->publish(
                new PublishableLineCommentMessageCollection(
                    event: $event,
                    messages: [
                        new PublishablePartialBranchLineCommentMessage(
                            event: $event,
                            fileName: 'mock-file',
                            startLineNumber: 1,
                            endLineNumber: 1,
                            totalBranches: 2,
                            coveredBranches: 1
                        )
                    ]
                )
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
                    tag: new Tag('mock-tag', 'mock-commit'),
                    baseCommit: 'mock-base-commit'
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
                    tag: new Tag('mock-tag', 'mock-commit'),
                    pullRequest: '1234',
                    baseCommit: 'mock-base-commit',
                    baseRef: 'main',
                ),
                true
            ]
        ];
    }
}
