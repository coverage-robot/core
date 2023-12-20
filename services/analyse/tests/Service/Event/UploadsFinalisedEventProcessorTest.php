<?php

namespace App\Tests\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\ReportComparison;
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
            ['mock-parent-1'],
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

        $mockCoverageAnalyserService->expects($this->never())
            ->method('compare');

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
                        $this->assertEquals(
                            null,
                            $message->getMessages()[1]->getCoverageChange()
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

    public function testProcessingEventWithBaseComparisonFromHistory(): void
    {
        $uploadsFinalised = new UploadsFinalised(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            ['mock-parent-1'],
            1,
            'mock-base-commit',
            'main',
            new DateTimeImmutable()
        );

        $headWaypoint = new ReportWaypoint(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-base-commit',
            null,
            [
                [
                    'commit' => 'mock-commit',
                    'isOnBaseRef' => false
                ],
                [
                    'commit' => 'mock-parent-base-commit',
                    'isOnBaseRef' => true
                ],
            ],
            []
        );

        $baseWaypoint = new ReportWaypoint(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-parent-base-commit',
            null,
            [],
            []
        );

        $mockHeadReport = $this->createMock(ReportInterface::class);
        $mockHeadReport->method('getCoveragePercentage')
            ->willReturn(91.0);

        $mockBaseReport = $this->createMock(ReportInterface::class);
        $mockBaseReport->method('getCoveragePercentage')
            ->willReturn(90.0);

        $reportComparison = new ReportComparison(
            $mockBaseReport,
            $mockHeadReport,
        );

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('getWaypointFromEvent')
            ->willReturn($headWaypoint);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('getWaypoint')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'main',
                'mock-parent-base-commit'
            )
            ->willReturn($baseWaypoint);
        $mockCoverageAnalyserService->expects($this->exactly(2))
            ->method('analyse')
            ->willReturnMap([
                [
                    $headWaypoint,
                    $mockHeadReport
                ],
                [
                    $baseWaypoint,
                    $mockBaseReport
                ]
            ]);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('compare')
            ->with($mockBaseReport, $mockHeadReport)
            ->willReturn($reportComparison);

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
                        $this->assertEquals(
                            1,
                            $message->getMessages()[1]
                                ->getCoverageChange()
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

    public function testProcessingEventWithBaseComparisonFromEvent(): void
    {
        $uploadsFinalised = new UploadsFinalised(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            ['mock-parent-1'],
            1,
            'mock-base-commit',
            'main',
            new DateTimeImmutable()
        );

        $headWaypoint = new ReportWaypoint(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-base-commit',
            null,
            [],
            []
        );

        $baseWaypoint = new ReportWaypoint(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-base-commit',
            null,
            [],
            []
        );

        $mockHeadReport = $this->createMock(ReportInterface::class);
        $mockHeadReport->method('getCoveragePercentage')
            ->willReturn(91.0);
        $mockBaseReport = $this->createMock(ReportInterface::class);
        $mockBaseReport->method('getCoveragePercentage')
            ->willReturn(90.0);

        $reportComparison = new ReportComparison(
            $mockBaseReport,
            $mockHeadReport,
        );

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('getWaypointFromEvent')
            ->willReturn($headWaypoint);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('getWaypoint')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'main',
                'mock-base-commit'
            )
            ->willReturn($baseWaypoint);
        $mockCoverageAnalyserService->expects($this->exactly(2))
            ->method('analyse')
            ->willReturnMap([
                [
                    $headWaypoint,
                    $mockHeadReport
                ],
                [
                    $baseWaypoint,
                    $mockBaseReport
                ]
            ]);

        $mockCoverageAnalyserService->expects($this->once())
            ->method('compare')
            ->with($mockBaseReport, $mockHeadReport)
            ->willReturn($reportComparison);

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
                        $this->assertEquals(
                            1,
                            $message->getMessages()[1]
                                ->getCoverageChange()
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
            ['mock-parent-1'],
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
