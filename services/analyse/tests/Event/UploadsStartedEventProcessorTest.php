<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Event\UploadsStartedEventProcessor;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\UploadsStarted;
use Packages\Message\Client\SqsClientInterface;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class UploadsStartedEventProcessorTest extends TestCase
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
            projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit'
        );

        $mockPublishClient = $this->createMock(SqsClientInterface::class);
        $mockPublishClient->expects($this->once())
            ->method('dispatch')
            ->with(
                self::callback(
                    function (PublishableCheckRunMessage $message) use ($uploadsStarted): bool {
                        $this->assertSame(
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
