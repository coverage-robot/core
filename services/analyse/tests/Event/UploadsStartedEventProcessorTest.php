<?php

namespace App\Tests\Event;

use App\Event\UploadsStartedEventProcessor;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\UploadsStarted;
use Packages\Message\Client\PublishClient;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class UploadsStartedEventProcessorTest extends TestCase
{
    public function testGetEvent(): void
    {
        $this->assertEquals(
            Event::UPLOADS_STARTED->value,
            UploadsStartedEventProcessor::getEvent()
        );
    }

    public function testProcessingEvent(): void
    {
        $uploadsStarted = new UploadsStarted(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit'
        );

        $mockPublishClient = $this->createMock(PublishClient::class);
        $mockPublishClient->expects($this->once())
            ->method('dispatch')
            ->with(
                self::callback(
                    function (PublishableCheckRunMessage $message) use ($uploadsStarted) {
                        $this->assertEquals(
                            PublishableCheckRunStatus::IN_PROGRESS,
                            $message->getStatus()
                        );
                        $this->assertEquals(
                            $uploadsStarted,
                            $message->getEvent()
                        );
                        return true;
                    }
                )
            )
            ->willReturn(true);

        $uploadsStartedEventProcessor = new UploadsStartedEventProcessor(
            new NullLogger(),
            $mockPublishClient
        );

        $this->assertTrue(
            $uploadsStartedEventProcessor->process($uploadsStarted)
        );
    }
}
