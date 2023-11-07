<?php

namespace App\Tests\Service\Event;

use App\Client\SqsMessageClient;
use App\Service\Event\UploadsStartedEventProcessor;
use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Event\Model\UploadsStarted;
use Packages\Models\Enum\Provider;
use Packages\Models\Enum\PublishableCheckRunStatus;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
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
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            null,
            new DateTimeImmutable()
        );

        $sqsMessageClient = $this->createMock(SqsMessageClient::class);
        $sqsMessageClient->expects($this->once())
            ->method('queuePublishableMessage')
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
            $sqsMessageClient
        );

        $this->assertTrue(
            $uploadsStartedEventProcessor->process($uploadsStarted)
        );
    }
}
