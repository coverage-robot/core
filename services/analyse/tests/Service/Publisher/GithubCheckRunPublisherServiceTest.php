<?php

namespace App\Tests\Service\Publisher;

use App\Client\Github\GithubAppClient;
use App\Client\Github\GithubAppInstallationClient;
use App\Enum\ProviderEnum;
use App\Exception\PublishException;
use App\Model\PublishableCoverageDataInterface;
use App\Model\Upload;
use App\Service\Publisher\GithubCheckRunPublisherService;
use App\Service\Publisher\GithubPullRequestCommentPublisherService;
use App\Tests\Mock\Factory\MockPublishableCoverageDataFactory;
use Github\Api\Repo;
use Github\Api\Repository\Checks\CheckRuns;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GithubCheckRunPublisherServiceTest extends TestCase
{
    public function testGetPriority(): void
    {
        $this->assertTrue(
            GithubCheckRunPublisherService::getPriority() < GithubPullRequestCommentPublisherService::getPriority()
        );
    }

    #[DataProvider('supportsDataProvider')]
    public function testSupports(Upload $upload, bool $expectedSupport): void
    {
        $publisher = new GithubCheckRunPublisherService(
            $this->createMock(GithubAppInstallationClient::class),
            new NullLogger()
        );

        $isSupported = $publisher->supports($upload, MockPublishableCoverageDataFactory::createMock($this));

        $this->assertEquals($expectedSupport, $isSupported);
    }

    #[DataProvider('supportsDataProvider')]
    public function testPublishToNewCheckRun(Upload $upload, bool $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $publisher = new GithubCheckRunPublisherService(
            $mockGithubAppInstallationClient,
            new NullLogger()
        );

        if (!$expectedSupport) {
            $this->expectExceptionObject(PublishException::notSupportedException());
        }

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);

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

        $mockCheckRunsApi->expects($this->once())
            ->method('create');

        $mockCheckRunsApi->expects($this->never())
            ->method('update');

        $publisher->publish($upload, $mockPublishableCoverageData);
    }

    #[DataProvider('supportsDataProvider')]
    public function testPublishToExistingCheckRun(Upload $upload, bool $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $publisher = new GithubCheckRunPublisherService(
            $mockGithubAppInstallationClient,
            new NullLogger()
        );

        if (!$expectedSupport) {
            $this->expectExceptionObject(PublishException::notSupportedException());
        }

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);

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
                            'id' => GithubAppClient::APP_ID
                        ]
                    ]
                ]
            ]);

        $mockCheckRunsApi->expects($this->never())
            ->method('create');

        $mockCheckRunsApi->expects($this->once())
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
                        'parent' => 'mock-parent'
                    ]
                ),
                true
            ]
        ];
    }
}
