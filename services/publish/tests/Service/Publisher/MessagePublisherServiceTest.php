<?php

namespace App\Tests\Service\Publisher;

use App\Service\Publisher\MessagePublisherService;
use App\Service\Publisher\PublisherServiceInterface;
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Telemetry\Service\MetricServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class MessagePublisherServiceTest extends TestCase
{
    public function testPublishingMessagesToOnlySupportedPublishers(): void
    {
        $mockSupportedPublisher = $this->createMock(PublisherServiceInterface::class);
        $mockSupportedPublisher->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $mockSupportedPublisher->expects($this->once())
            ->method('publish')
            ->willReturn(true);

        $mockUnsupportedPublisher = $this->createMock(PublisherServiceInterface::class);
        $mockUnsupportedPublisher->expects($this->once())
            ->method('supports')
            ->willReturn(false);
        $mockUnsupportedPublisher->expects($this->never())
            ->method('publish');

        $mockMessage = $this->createMock(PublishableMessageInterface::class);
        $mockMessage->expects($this->once())
            ->method('getType')
            ->willReturn(PublishableMessage::PULL_REQUEST);

        $messagePublisherService = new MessagePublisherService(
            [
                $mockSupportedPublisher,
                $mockUnsupportedPublisher
            ],
            new NullLogger(),
            $this->createMock(MetricServiceInterface::class)
        );

        $this->assertTrue($messagePublisherService->publish($mockMessage));
    }
}
