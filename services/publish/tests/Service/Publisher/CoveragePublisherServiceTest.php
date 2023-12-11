<?php

namespace App\Tests\Service\Publisher;

use App\Service\Publisher\MessagePublisherService;
use App\Tests\Mock\Factory\MockPublisherFactory;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Packages\Telemetry\Service\MetricService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CoveragePublisherServiceTest extends TestCase
{
    public function testPublishToOnlySupportedPublishers(): void
    {
        $supportedPublisher = MockPublisherFactory::getMockPublisher($this);
        $unsupportedPublisher = MockPublisherFactory::getMockPublisher($this, false, false);

        $supportedPublisher->expects($this->once())
            ->method('publish');
        $unsupportedPublisher->expects($this->never())
            ->method('publish');

        $message = $this->createMock(PublishablePullRequestMessage::class);
        $message->method('getType')
            ->willReturn(PublishableMessage::PULL_REQUEST);

        $publisher = new MessagePublisherService(
            [
                $supportedPublisher,
                $unsupportedPublisher
            ],
            new NullLogger(),
            $this->createMock(MetricService::class)
        );

        $this->assertTrue($publisher->publish($message));
    }
}
