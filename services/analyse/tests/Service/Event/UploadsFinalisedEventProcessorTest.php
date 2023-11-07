<?php

namespace App\Tests\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\PublishableCoverageDataInterface;
use App\Service\CoverageAnalyserService;
use App\Service\Event\UploadsFinalisedEventProcessor;
use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Event\Model\AnalyseFailure;
use Packages\Event\Model\CoverageFinalised;
use Packages\Event\Model\UploadsFinalised;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
use Packages\Models\Model\PublishableMessage\PublishableMessageCollection;
use Packages\Models\Model\PublishableMessage\PublishablePullRequestMessage;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class UploadsFinalisedEventProcessorTest extends KernelTestCase
{
    public function testGetEvent(): void
    {
        $this->assertEquals(
            Event::UPLOADS_FINALISED->value,
            UploadsFinalisedEventProcessor::getEvent()
        );
    }

    public function testProcessingEventSuccessfullyTriggersFinalisedEvent(): void
    {
        $uploadsFinalised = new UploadsFinalised(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            null,
            new DateTimeImmutable()
        );

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->with($uploadsFinalised)
            ->willReturn($mockPublishableCoverageData);

        $mockEventBridgeEventService = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventService->expects($this->once())
            ->method('publishEvent')
            ->with($this->isInstanceOf(CoverageFinalised::class))
            ->willReturn(true);

        $mockSqsMessageClient = $this->createMock(SqsMessageClient::class);
        $mockSqsMessageClient->expects($this->once())
            ->method('queuePublishableMessage')
            ->with(
                self::callback(
                    function (PublishableMessageCollection $message) use ($uploadsFinalised) {
                        $this->assertEquals(
                            $uploadsFinalised,
                            $message->getEvent()
                        );
                        $this->assertEquals(
                            2,
                            $message->count()
                        );
                        $this->assertInstanceOf(
                            PublishablePullRequestMessage::class,
                            $message->getMessages()[0]
                        );
                        $this->assertInstanceOf(
                            PublishableCheckRunMessage::class,
                            $message->getMessages()[1]
                        );
                        return true;
                    }
                )
            )
            ->willReturn(true);

        $uploadsFinalisedEventProcessor = new UploadsFinalisedEventProcessor(
            new NullLogger(),
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageAnalyserService,
            $mockEventBridgeEventService,
            $mockSqsMessageClient
        );

        $this->assertTrue(
            $uploadsFinalisedEventProcessor->process(
                $uploadsFinalised
            )
        );
    }

    public function testProcessingEventUnsuccessfullyTriggersFailureEvent(): void
    {
        $uploadsFinalised = new UploadsFinalised(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            null,
            new DateTimeImmutable()
        );

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->with($uploadsFinalised)
            ->willReturn($mockPublishableCoverageData);

        $mockEventBridgeEventService = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventService->expects($this->once())
            ->method('publishEvent')
            ->with($this->isInstanceOf(AnalyseFailure::class))
            ->willReturn(true);

        $mockSqsMessageClient = $this->createMock(SqsMessageClient::class);
        $mockSqsMessageClient->expects($this->once())
            ->method('queuePublishableMessage')
            ->with(
                self::callback(
                    function (PublishableMessageCollection $message) use ($uploadsFinalised) {
                        $this->assertEquals(
                            $uploadsFinalised,
                            $message->getEvent()
                        );
                        $this->assertEquals(
                            2,
                            $message->count()
                        );
                        $this->assertInstanceOf(
                            PublishablePullRequestMessage::class,
                            $message->getMessages()[0]
                        );
                        $this->assertInstanceOf(
                            PublishableCheckRunMessage::class,
                            $message->getMessages()[1]
                        );
                        return true;
                    }
                )
            )
            ->willReturn(false);

        $uploadsFinalisedEventProcessor = new UploadsFinalisedEventProcessor(
            new NullLogger(),
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageAnalyserService,
            $mockEventBridgeEventService,
            $mockSqsMessageClient
        );

        $this->assertFalse(
            $uploadsFinalisedEventProcessor->process(
                $uploadsFinalised
            )
        );
    }
}
