<?php

namespace App\Tests\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\PublishableCoverageDataInterface;
use App\Model\ReportInterface;
use App\Model\ReportWaypoint;
use App\Service\CoverageAnalyserService;
use App\Service\Event\UploadsFinalisedEventProcessor;
use App\Service\LineGroupingService;
use DateTimeImmutable;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\AnalyseFailure;
use Packages\Event\Model\CoverageFinalised;
use Packages\Event\Model\UploadsFinalised;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableMessageCollection;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
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
            null,
            null,
            new DateTimeImmutable()
        );

        $mockReport = $this->createMock(ReportInterface::class);

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->with($this->isInstanceOf(ReportWaypoint::class))
            ->willReturn($mockReport);

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
            new LineGroupingService(new NullLogger()),
            $mockEventBridgeEventService,
            $mockSqsMessageClient
        );

        $this->assertTrue(
            $uploadsFinalisedEventProcessor->process($uploadsFinalised)
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
            null,
            null,
            new DateTimeImmutable()
        );

        $mockReport = $this->createMock(ReportInterface::class);

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->with($this->isInstanceOf(ReportWaypoint::class))
            ->willReturn($mockReport);

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
            new LineGroupingService(new NullLogger()),
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
