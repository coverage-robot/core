<?php

namespace App\Tests\Service\Publisher\Github;

use App\Enum\EnvironmentVariable;
use App\Exception\PublishException;
use App\Service\Formatter\CheckAnnotationFormatterService;
use App\Service\Formatter\CheckRunFormatterService;
use App\Service\Publisher\Github\GithubCheckRunPublisherService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use DateTimeImmutable;
use Github\Api\Repo;
use Github\Api\Repository\Checks\CheckRuns;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\Upload;
use Packages\Message\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use Packages\Message\PublishableMessage\PublishableMissingCoverageAnnotationMessage;
use Packages\Message\PublishableMessage\PublishablePartialBranchAnnotationMessage;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response;

class GithubCheckRunPublisherServiceTest extends TestCase
{
    #[DataProvider('uploadsDataProvider')]
    public function testSupports(Upload $upload, bool $expectedSupport): void
    {
        $publisher = new GithubCheckRunPublisherService(
            new CheckRunFormatterService(),
            new CheckAnnotationFormatterService(),
            $this->createMock(GithubAppInstallationClient::class),
            MockEnvironmentServiceFactory::getMock($this, Environment::TESTING),
            new NullLogger()
        );

        $this->assertEquals(
            $expectedSupport,
            $publisher->supports(
                new PublishableCheckRunMessage(
                    $upload,
                    PublishableCheckRunStatus::SUCCESS,
                    [],
                    100,
                    'mock-base-commit',
                    0,
                    new DateTimeImmutable()
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
            MockEnvironmentServiceFactory::getMock(
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
                $upload,
                PublishableCheckRunStatus::SUCCESS,
                [],
                100,
                'mock-base-commit',
                0,
                new DateTimeImmutable()
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
            MockEnvironmentServiceFactory::getMock(
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
                    function (array $checkRun) {
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
                $upload,
                PublishableCheckRunStatus::SUCCESS,
                [
                    new PublishableMissingCoverageAnnotationMessage(
                        $upload,
                        'mock-file.php',
                        false,
                        1,
                        5,
                        new DateTimeImmutable()
                    )
                ],
                100,
                'mock-base-commit',
                0,
                new DateTimeImmutable()
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
            MockEnvironmentServiceFactory::getMock(
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
                $upload,
                PublishableCheckRunStatus::IN_PROGRESS,
                array_fill(
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
                100,
                'mock-base-commit',
                0,
                new DateTimeImmutable()
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
            MockEnvironmentServiceFactory::getMock(
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
                $upload,
                PublishableCheckRunStatus::IN_PROGRESS,
                [],
                100,
                'mock-base-commit',
                0,
                new DateTimeImmutable()
            )
        );
    }

    public static function uploadsDataProvider(): array
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
                    null,
                    null,
                    null,
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
                    '1234',
                    'commit-on-main',
                    'main',
                    new Tag('mock-tag', 'mock-commit'),
                ),
                true
            ]
        ];
    }
}
