<?php

namespace App\Tests\Service\Publisher\Github;

use App\Enum\EnvironmentVariable;
use App\Exception\PublishException;
use App\Service\Formatter\CheckAnnotationFormatterService;
use App\Service\Formatter\CheckRunFormatterService;
use App\Service\Publisher\Github\GithubCheckRunPublisherService;
use App\Service\Templating\TemplateRenderingService;
use Github\Api\Repo;
use Github\Api\Repository\Checks\CheckRuns;
use Packages\Clients\Client\Github\GithubAppInstallationClientInterface;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\Upload;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;

final class GithubCheckRunPublisherServiceTest extends KernelTestCase
{
    #[DataProvider('uploadsDataProvider')]
    public function testSupports(Upload $upload, bool $expectedSupport): void
    {
        $publisher = new GithubCheckRunPublisherService(
            $this->getContainer()
                ->get(TemplateRenderingService::class),
            $this->createMock(GithubAppInstallationClientInterface::class),
            MockEnvironmentServiceFactory::createMock($this, Environment::TESTING),
            new NullLogger()
        );

        $this->assertEquals(
            $expectedSupport,
            $publisher->supports(
                new PublishableCheckRunMessage(
                    event: $upload,
                    status: PublishableCheckRunStatus::SUCCESS,
                    coveragePercentage: 100,
                    baseCommit: 'mock-base-commit'
                )
            )
        );
    }

    #[DataProvider('uploadsDataProvider')]
    public function testPublishToNewCheckRun(Upload $upload, bool $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClientInterface::class);
        $publisher = new GithubCheckRunPublisherService(
            $this->getContainer()
                ->get(TemplateRenderingService::class),
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

        if (!$expectedSupport) {
            $this->expectExceptionObject(PublishException::notSupportedException());
        }

        $mockGithubAppInstallationClient->expects($this->once())
            ->method('authenticateAsRepositoryOwner')
            ->with($upload->getOwner());

        $mockRepoApi = $this->createMock(Repo::class);
        $mockCheckRunsApi = $this->createMock(CheckRuns::class);

        $mockGithubAppInstallationClient->method('repo')
            ->willReturn($mockRepoApi);

        $mockRepoApi->method('checkRuns')
            ->willReturn($mockCheckRunsApi);

        $mockCheckRunsApi->expects($this->once())
            ->method('allForReference')
            ->with(
                $upload->getOwner(),
                $upload->getRepository(),
                $upload->getCommit(),
                ['app_id' => 'mock-github-app-id']
            )
            ->willReturn([
                'check_runs' => []
            ]);

        $mockGithubAppInstallationClient
            ->method('getLastResponse')
            ->willReturn(new \Nyholm\Psr7\Response(Response::HTTP_CREATED));

        $mockCheckRunsApi->expects($this->once())
            ->method('create')
            ->willReturn([
                'id' => 3
            ]);

        $mockCheckRunsApi->expects($this->never())
            ->method('update');

        $publisher->publish(
            new PublishableCheckRunMessage(
                event: $upload,
                status: PublishableCheckRunStatus::SUCCESS,
                coveragePercentage: 100,
                baseCommit: 'mock-base-commit',
            )
        );
    }

    #[DataProvider('uploadsDataProvider')]
    public function testPublishToExistingCheckRun(Upload $upload, bool $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClientInterface::class);
        $publisher = new GithubCheckRunPublisherService(
            $this->getContainer()
                ->get(TemplateRenderingService::class),
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

        if (!$expectedSupport) {
            $this->expectExceptionObject(PublishException::notSupportedException());
        }

        $mockGithubAppInstallationClient->expects($this->once())
            ->method('authenticateAsRepositoryOwner')
            ->with($upload->getOwner());

        $mockRepoApi = $this->createMock(Repo::class);
        $mockCheckRunsApi = $this->createMock(CheckRuns::class);

        $mockGithubAppInstallationClient->expects($this->exactly(2))
            ->method('repo')
            ->willReturn($mockRepoApi);

        $mockRepoApi->expects($this->exactly(2))
            ->method('checkRuns')
            ->willReturn($mockCheckRunsApi);

        $mockCheckRunsApi->expects($this->once())
            ->method('allForReference')
            ->willReturn([
                'check_runs' => [
                    [
                        'id' => 1,
                        'conclusion' => 'success',
                        'app' => [
                            'id' => 'app-1'
                        ]
                    ],
                    [
                        'id' => 2,
                        'conclusion' => 'success',
                        'app' => [
                            'id' => 'mock-github-app-id'
                        ]
                    ]
                ]
            ]);

        $mockCheckRunsApi->expects($this->never())
            ->method('create');

        $mockCheckRunsApi->expects($this->once())
            ->method('update');

        $publisher->publish(
            new PublishableCheckRunMessage(
                event: $upload,
                status: PublishableCheckRunStatus::IN_PROGRESS,
                coveragePercentage: 100,
                baseCommit: 'mock-base-commit'
            )
        );
    }

    public static function uploadsDataProvider(): array
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
