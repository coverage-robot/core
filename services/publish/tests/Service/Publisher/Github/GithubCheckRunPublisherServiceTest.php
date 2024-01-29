<?php

namespace App\Tests\Service\Publisher\Github;

use App\Enum\EnvironmentVariable;
use App\Exception\PublishException;
use App\Service\Formatter\CheckAnnotationFormatterService;
use App\Service\Formatter\CheckRunFormatterService;
use App\Service\Publisher\Github\GithubCheckRunPublisherService;
use DateTimeImmutable;
use Github\Api\Repo;
use Github\Api\Repository\Checks\CheckRuns;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\Upload;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use Packages\Message\PublishableMessage\PublishableMissingCoverageAnnotationMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchAnnotationMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response;

final class GithubCheckRunPublisherServiceTest extends TestCase
{
    #[DataProvider('uploadsDataProvider')]
    public function testSupports(Upload $upload, bool $expectedSupport): void
    {
        $publisher = new GithubCheckRunPublisherService(
            new CheckRunFormatterService(),
            new CheckAnnotationFormatterService(),
            $this->createMock(GithubAppInstallationClient::class),
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
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $publisher = new GithubCheckRunPublisherService(
            new CheckRunFormatterService(),
            new CheckAnnotationFormatterService(),
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
                        'app' => [
                            'id' => 'app-1'
                        ]
                    ],
                    [
                        'id' => 2,
                        'app' => [
                            'id' => 'app-2'
                        ]
                    ]
                ]
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
    public function testPublishAnnotationsToCheckRun(Upload $upload, bool $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $publisher = new GithubCheckRunPublisherService(
            new CheckRunFormatterService(),
            new CheckAnnotationFormatterService(),
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

        $mockGithubAppInstallationClient->expects($this->exactly(3))
            ->method('repo')
            ->willReturn($mockRepoApi);

        $mockRepoApi->expects($this->exactly(3))
            ->method('checkRuns')
            ->willReturn($mockCheckRunsApi);

        $mockCheckRunsApi->expects($this->once())
            ->method('allForReference')
            ->willReturn([
                'check_runs' => [
                    [
                        'id' => 1,
                        'app' => [
                            'id' => 'app-1'
                        ]
                    ],
                    [
                        'id' => 2,
                        'app' => [
                            'id' => 'app-2'
                        ]
                    ]
                ]
            ]);

        $mockGithubAppInstallationClient
            ->method('getLastResponse')
            ->willReturn(new \Nyholm\Psr7\Response(Response::HTTP_CREATED));

        $mockCheckRunsApi->expects($this->once())
            ->method('create')
            ->willReturn([
                'id' => 3
            ]);

        $mockCheckRunsApi->expects($this->once())
            ->method('update')
            ->with(
                $upload->getOwner(),
                $upload->getRepository(),
                3,
                self::callback(
                    function (array $checkRun): bool {
                        $this->assertEquals('Coverage Robot', $checkRun['name']);
                        $this->assertEquals('completed', $checkRun['status']);
                        $this->assertEquals('success', $checkRun['conclusion']);
                        $this->assertEquals(
                            [
                                'title' => 'Total Coverage: 100% (no change compared to mock-ba)',
                                'summary' => '',
                                'annotations' => [
                                    [
                                        'path' => 'mock-file.php',
                                        'annotation_level' => 'warning',
                                        'title' => 'Opportunity For New Coverage',
                                        'message' => 'The next 4 lines are not covered by any tests.',
                                        'start_line' => 1,
                                        'end_line' => 1
                                    ]
                                ]
                            ],
                            $checkRun['output']
                        );
                        return true;
                    }
                )
            );

        $publisher->publish(
            new PublishableCheckRunMessage(
                event: $upload,
                status: PublishableCheckRunStatus::SUCCESS,
                coveragePercentage: 100,
                annotations: [
                    new PublishableMissingCoverageAnnotationMessage(
                        event: $upload,
                        fileName: 'mock-file.php',
                        startingOnMethod: false,
                        startLineNumber: 1,
                        endLineNumber: 5
                    )
                ],
                baseCommit: 'mock-base-commit'
            )
        );
    }

    #[DataProvider('uploadsDataProvider')]
    public function testPublishMultipleChunksOfAnnotationsToCheckRun(Upload $upload, bool $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $publisher = new GithubCheckRunPublisherService(
            new CheckRunFormatterService(),
            new CheckAnnotationFormatterService(),
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

        $mockGithubAppInstallationClient->expects($this->exactly(4))
            ->method('repo')
            ->willReturn($mockRepoApi);

        $mockRepoApi->expects($this->exactly(4))
            ->method('checkRuns')
            ->willReturn($mockCheckRunsApi);

        $mockCheckRunsApi->expects($this->once())
            ->method('allForReference')
            ->willReturn([
                'check_runs' => [
                    [
                        'id' => 1,
                        'app' => [
                            'id' => 'app-1'
                        ]
                    ],
                    [
                        'id' => 2,
                        'app' => [
                            'id' => 'app-2'
                        ]
                    ]
                ]
            ]);

        $mockGithubAppInstallationClient
            ->method('getLastResponse')
            ->willReturn(new \Nyholm\Psr7\Response(Response::HTTP_CREATED));

        $mockCheckRunsApi->expects($this->once())
            ->method('create')
            ->willReturn([
                'id' => 3
            ]);

        $mockCheckRunsApi->expects($this->exactly(2))
            ->method('update');

        $publisher->publish(
            new PublishableCheckRunMessage(
                event: $upload,
                status: PublishableCheckRunStatus::IN_PROGRESS,
                coveragePercentage: 100,
                annotations: array_fill(
                    0,
                    52,
                    new PublishablePartialBranchAnnotationMessage(
                        $upload,
                        'mock-file.php',
                        1,
                        1,
                        5,
                        4,
                        new DateTimeImmutable()
                    )
                ),
                baseCommit: 'mock-base-commit'
            )
        );
    }

    #[DataProvider('uploadsDataProvider')]
    public function testPublishToExistingCheckRun(Upload $upload, bool $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $publisher = new GithubCheckRunPublisherService(
            new CheckRunFormatterService(),
            new CheckAnnotationFormatterService(),
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
                        'output' => [
                            'annotations_count' => 0,
                        ],
                        'conclusion' => 'success',
                        'app' => [
                            'id' => 'app-1'
                        ]
                    ],
                    [
                        'id' => 2,
                        'output' => [
                            'annotations_count' => 0,
                        ],
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
                    baseCommit: 'commit-on-main',
                    baseRef: 'main',
                ),
                true
            ]
        ];
    }
}
