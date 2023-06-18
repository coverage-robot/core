<?php

namespace App\Tests\Service\Publisher\Github;

use App\Client\Github\GithubAppClient;
use App\Client\Github\GithubAppInstallationClient;
use App\Exception\PublishException;
use App\Model\QueryResult\MultiLineCoverageQueryResult;
use App\Service\Formatter\CheckAnnotationFormatterService;
use App\Service\Publisher\Github\GithubCheckAnnotationPublisherService;
use App\Service\Publisher\Github\GithubCheckRunPublisherService;
use App\Tests\Mock\Factory\MockPublishableCoverageDataFactory;
use Github\Api\Repo;
use Github\Api\Repository\Checks\CheckRuns;
use Packages\Models\Enum\LineState;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GithubCheckAnnotationPublisherServiceTest extends TestCase
{
    public function testGetPriority(): void
    {
        $this->assertTrue(
            GithubCheckAnnotationPublisherService::getPriority() < GithubCheckRunPublisherService::getPriority()
        );
    }

    #[DataProvider('supportsDataProvider')]
    public function testPublish(Upload $upload, bool $expectedSupport): void
    {
        $mockGithubAppInstallationClient = $this->createMock(GithubAppInstallationClient::class);
        $publisher = new GithubCheckAnnotationPublisherService(
            $mockGithubAppInstallationClient,
            new NullLogger(),
            new CheckAnnotationFormatterService()
        );

        if (!$expectedSupport) {
            $this->expectExceptionObject(PublishException::notSupportedException());
        }

        $mockPublishableCoverageData = MockPublishableCoverageDataFactory::createMock(
            $this,
            [
                'getDiffLineCoverage' => MultiLineCoverageQueryResult::from([
                    [
                        'fileName' => 'file-1',
                        'lineNumber' => 1,
                        'state' => LineState::COVERED->value
                    ],
                    [
                        'fileName' => 'file-1',
                        'lineNumber' => 2,
                        'state' => LineState::UNCOVERED->value
                    ],
                ])
            ]
        );

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
                            'id' => GithubAppClient::APP_ID
                        ]
                    ]
                ]
            ]);

        $mockCheckRunsApi->expects($this->exactly($expectedSupport ? 1 : 0))
            ->method('update');

        $publisher->publish($upload, $mockPublishableCoverageData);
    }

    #[DataProvider('supportsDataProvider')]
    public function testSupports(Upload $upload, bool $expectedSupport): void
    {
        $publisher = new GithubCheckAnnotationPublisherService(
            $this->createMock(GithubAppInstallationClient::class),
            new NullLogger(),
            new CheckAnnotationFormatterService()
        );

        $isSupported = $publisher->supports($upload, MockPublishableCoverageDataFactory::createMock($this));

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
