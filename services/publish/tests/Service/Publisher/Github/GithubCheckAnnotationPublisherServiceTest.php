<?php

namespace App\Tests\Service\Publisher\Github;

use App\Enum\EnvironmentVariable;
use App\Exception\PublishException;
use App\Service\Formatter\CheckAnnotationFormatterService;
use App\Service\Formatter\CheckRunFormatterService;
use App\Service\Publisher\Github\GithubCheckAnnotationPublisherService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use DateTimeImmutable;
use Github\Api\Repo;
use Github\Api\Repository\Checks\CheckRuns;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\LineState;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Models\Model\PublishableMessage\PublishableCheckAnnotationMessageCollection;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GithubCheckAnnotationPublisherServiceTest extends TestCase
{
    #[DataProvider('supportsDataProvider')]
    public function testPublish(Upload $upload, bool $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $publisher = new GithubCheckAnnotationPublisherService(
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
            new NullLogger(),
        );

        if (!$expectedSupport) {
            $this->expectExceptionObject(PublishException::notSupportedException());
        }

        $mockGithubAppInstallationClient->expects($this->exactly($expectedSupport ? 1 : 0))
            ->method('authenticateAsRepositoryOwner')
            ->with($upload->getOwner());

        $mockRepoApi = $this->createMock(Repo::class);
        $mockCheckRunsApi = $this->createMock(CheckRuns::class);

        $mockGithubAppInstallationClient->expects($this->exactly($expectedSupport ? 2 : 0))
            ->method('repo')
            ->willReturn($mockRepoApi);

        $mockRepoApi->expects($this->exactly($expectedSupport ? 2 : 0))
            ->method('checkRuns')
            ->willReturn($mockCheckRunsApi);

        $mockCheckRunsApi->expects($this->never())
            ->method('create');

        $mockCheckRunsApi->expects($this->exactly($expectedSupport ? 1 : 0))
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
                            'id' => 'mock-github-app-id'
                        ]
                    ]
                ]
            ]);

        $mockCheckRunsApi->expects($this->exactly($expectedSupport ? 1 : 0))
            ->method('update');

        $publisher->publish(
            new PublishableCheckAnnotationMessageCollection(
                $upload,
                [
                    (new PublishableCheckAnnotationMessage(
                        $upload,
                        "mock-file",
                        1,
                        LineState::COVERED,
                        new DateTimeImmutable()
                    ))->jsonSerialize()
                ]
            )
        );
    }

    #[DataProvider('supportsDataProvider')]
    public function testSupports(Upload $upload, bool $expectedSupport): void
    {
        $publisher = new GithubCheckAnnotationPublisherService(
            new CheckRunFormatterService(),
            new CheckAnnotationFormatterService(),
            $this->createMock(GithubAppInstallationClient::class),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING
            ),
            new NullLogger()
        );

        $isSupported = $publisher->supports(
            new PublishableCheckAnnotationMessageCollection(
                $upload,
                [
                    (new PublishableCheckAnnotationMessage(
                        $upload,
                        "mock-file",
                        1,
                        LineState::COVERED,
                        new DateTimeImmutable()
                    ))->jsonSerialize()
                ]
            )
        );

        $this->assertEquals($expectedSupport, $isSupported);
    }

    public static function supportsDataProvider(): array
    {
        return [
            [
                Upload::from([
                    'uploadId' => 'mock-uuid',
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository',
                    'commit' => 'mock-commit',
                    'parent' => '["mock-parent"]',
                    'tag' => 'mock-tag',
                    'ref' => 'mock-ref',
                ]),
                false
            ],
            [
                Upload::from([
                    'uploadId' => 'mock-uuid',
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository',
                    'commit' => 'mock-commit',
                    'parent' => '["mock-parent"]',
                    'tag' => 'mock-tag',
                    'ref' => 'mock-ref',
                    'pullRequest' => 123
                ]),
                true
            ]
        ];
    }
}
