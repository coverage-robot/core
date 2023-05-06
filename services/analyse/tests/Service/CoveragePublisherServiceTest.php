<?php

namespace App\Tests\Service;

use App\Client\Github\GithubAppClient;
use App\Client\Github\GithubAppInstallationClient;
use App\Model\PublishableCoverageDataInterface;
use App\Model\Upload;
use App\Service\CoveragePublisherService;
use App\Service\Publisher\GithubCheckRunPublisherService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CoveragePublisherServiceTest extends TestCase
{
    public function testPublishGithubPullRequestComment(): void
    {
        $publisherService = new CoveragePublisherService(
            [
                new GithubCheckRunPublisherService(
                    new GithubAppInstallationClient(
                        new GithubAppClient(),
                        'ryanmab'
                    ),
                    new NullLogger()
                )
            //                new GithubPullRequestCommentPublisherService(
            //                    new GithubAppInstallationClient(
            //                        new GithubAppClient(),
            //                        "ryanmab"
            //                    ),
            //                    new NullLogger()
            //                )
            ]
        );

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);
        $mockPublishableCoverageData->method('getCoveragePercentage')
            ->willReturn(99.8);
        $mockPublishableCoverageData->method('getTotalLines')
            ->willReturn(100);
        $mockPublishableCoverageData->method('getAtLeastPartiallyCoveredLines')
            ->willReturn(97);

        $successful = $publisherService->publish(
            new Upload([
                'uploadId' => 'mock-uuid',
                'commit' => '6fc03961c51e4b5fb91f423ebdfd830b5fd11ed4',
                'provider' => 'github',
                'owner' => 'ryanmab',
                'repository' => 'portfolio',
                'pullRequest' => 1242
            ]),
            $mockPublishableCoverageData
        );

        $this->assertTrue($successful);
    }
}
