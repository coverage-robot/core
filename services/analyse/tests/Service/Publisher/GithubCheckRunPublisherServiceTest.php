<?php

namespace App\Tests\Service\Publisher;

use App\Client\Github\GithubAppInstallationClient;
use App\Enum\ProviderEnum;
use App\Model\PublishableCoverageDataInterface;
use App\Model\Upload;
use App\Service\Publisher\GithubCheckRunPublisherService;
use App\Service\Publisher\GithubPullRequestCommentPublisherService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GithubCheckRunPublisherServiceTest extends TestCase
{
    public function testGetPriority()
    {
        $this->assertTrue(
            GithubCheckRunPublisherService::getPriority() < GithubPullRequestCommentPublisherService::getPriority()
        );
    }

    #[DataProvider('supportsDataProvider')]
    public function testSupports(Upload $upload, bool $expectedSupport)
    {
        $publisher = new GithubCheckRunPublisherService(
            $this->createMock(GithubAppInstallationClient::class),
            new NullLogger()
        );

        $isSupported = $publisher->supports($upload, $this->createMock(PublishableCoverageDataInterface::class));

        $this->assertEquals($expectedSupport, $isSupported);
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
