<?php

namespace App\Tests\Service;

use App\Model\PublishableCoverageDataInterface;
use App\Service\CoveragePublisherService;
use App\Service\Publisher\GithubCheckRunPublisherService;
use App\Service\Publisher\GithubPullRequestCommentPublisherService;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CoveragePublisherServiceTest extends TestCase
{
    private const PUBLISHERS = [
        GithubCheckRunPublisherService::class,
        GithubPullRequestCommentPublisherService::class
    ];

    #[DataProvider('publisherDataProvider')]
    public function testParsingSupportedFiles(string $expectedPublisher): void
    {
        $mockUpload = $this->createMock(Upload::class);
        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);

        $mockedPublishers = [];
        foreach (self::PUBLISHERS as $publisher) {
            $mockPublisher = $this->createMock($publisher);
            $mockPublisher->expects($this->exactly(2))
                ->method('supports')
                ->with($mockUpload, $mockPublishableCoverageData)
                ->willReturn($expectedPublisher === $publisher);

            $mockPublisher->expects($expectedPublisher === $publisher ? $this->atLeastOnce() : $this->never())
                ->method('publish')
                ->with($mockUpload, $mockPublishableCoverageData)
                ->willReturn($expectedPublisher === $publisher);

            $mockedPublishers[] = $mockPublisher;
        }

        $coveragePublisherService = new CoveragePublisherService($mockedPublishers, new NullLogger());
        $coveragePublisherService->publish($mockUpload, $mockPublishableCoverageData);

        $this->assertTrue($coveragePublisherService->publish($mockUpload, $mockPublishableCoverageData));
    }

    public static function publisherDataProvider(): array
    {
        return array_map(static fn(string $strategy) => [$strategy], self::PUBLISHERS);
    }
}
