<?php

declare(strict_types=1);

namespace App\Tests\Service\Publisher;

use App\Exception\PublishingNotSupportedException;
use App\Service\MessagePublisherService;
use App\Service\Publisher\PublisherServiceInterface;
use Packages\Event\Model\EventInterface;
use Packages\Message\PublishableMessage\PublishableMessageInterface;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Packages\Telemetry\Service\MetricServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CoveragePublisherServiceTest extends TestCase
{
    public function testPublishToOnlySupportedPublishers(): void
    {
        $supportedPublisher = $this->getMockPublisher();
        $unsupportedPublisher = $this->getMockPublisher(false, false);

        $supportedPublisher->expects($this->once())
            ->method('publish');
        $unsupportedPublisher->expects($this->never())
            ->method('publish');

        $message = new PublishablePullRequestMessage(
            event: $this->createStub(EventInterface::class),
            coveragePercentage: 100,
            diffCoveragePercentage: 100,
            diffUncoveredLines: 1,
            successfulUploads: 1,
            tagCoverage: [],
            leastCoveredDiffFiles: [],
            uncoveredLinesChange: 2,
        );

        $publisher = new MessagePublisherService(
            [
                $supportedPublisher,
                $unsupportedPublisher
            ],
            new NullLogger(),
            $this->createStub(MetricServiceInterface::class)
        );

        $this->assertTrue($publisher->publish($message));
    }

    public function getMockPublisher(
        bool $supported = true,
        bool $publishSuccessfully = true
    ): MockObject {
        $mockPublisher = $this->createMock(PublisherServiceInterface::class);

        $mockPublisher->method('supports')
            ->willReturn($supported);

        if ($supported) {
            $mockPublisher->method('publish')
                ->willReturn($publishSuccessfully);
        } else {
            $mockPublisher->method('publish')
                ->willThrowException(
                    new PublishingNotSupportedException(
                        PublisherServiceInterface::class,
                        $this->createStub(PublishableMessageInterface::class)
                    )
                );
        }

        return $mockPublisher;
    }
}
