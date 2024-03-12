<?php

namespace App\Tests\Service\Publisher;

use App\Service\MessagePublisherService;
use App\Tests\Mock\Factory\MockPublisherFactory;
use Packages\Event\Model\EventInterface;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Packages\Telemetry\Service\MetricServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CoveragePublisherServiceTest extends TestCase
{
    public function testPublishToOnlySupportedPublishers(): void
    {
        $supportedPublisher = MockPublisherFactory::getMockPublisher($this);
        $unsupportedPublisher = MockPublisherFactory::getMockPublisher($this, false, false);

        $supportedPublisher->expects($this->once())
            ->method('publish');
        $unsupportedPublisher->expects($this->never())
            ->method('publish');

        $message = new PublishablePullRequestMessage(
            event: $this->createMock(EventInterface::class),
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
            $this->createMock(MetricServiceInterface::class)
        );

        $this->assertTrue($publisher->publish($message));
    }
}
