<?php

namespace App\Tests\Service\Publisher;

use App\Client\Github\GithubAppInstallationClient;
use App\Enum\ProviderEnum;
use App\Model\PublishableCoverageDataInterface;
use App\Model\Upload;
use App\Service\Publisher\GithubPullRequestCommentPublisherService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GithubPullRequestCommentPublisherServiceTest extends TestCase
{
    #[DataProvider('supportsDataProvider')]
    public function testSupports(Upload $upload, bool $expectedSupport)
    {
        $publisher = new GithubPullRequestCommentPublisherService(
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
                        'parent' => 'mock-parent',
                        'pullRequest' => '1234'
                    ]
                ),
                true
            ],
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
                false
            ]
        ];
    }
}
