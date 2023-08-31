<?php

namespace App\Tests\Service\Publisher;

use App\Service\Publisher\MessagePublisherService;
use App\Tests\Mock\Factory\MockPublisherFactory;
use Packages\Models\Model\PublishableMessage\PublishableMessageInterface;
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

        $publisher = new MessagePublisherService(
            [
                $supportedPublisher,
                $unsupportedPublisher
            ],
            new NullLogger()
        );

        $publisher->publish($this->createMock(PublishableMessageInterface::class));
    }
}
