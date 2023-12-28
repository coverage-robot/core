<?php

namespace App\Tests\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\ReportComparison;
use App\Model\ReportInterface;
use App\Model\ReportWaypoint;
use App\Service\CoverageAnalyserService;
use App\Service\CoverageComparisonService;
use App\Service\Event\UploadsFinalisedEventProcessor;
use App\Service\LineGroupingService;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Mock\MockSettingServiceFactory;
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
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            parent: []
        );

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->with($this->isInstanceOf(ReportWaypoint::class))
            ->willReturn($this->createMock(ReportInterface::class));

        $mockCoverageComparisonService = $this->createMock(CoverageComparisonService::class);
        $mockCoverageComparisonService->expects($this->once())
            ->method('getSuitableComparisonForWaypoint')
            ->willReturn(null);

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
            $mockCoverageComparisonService,
            new LineGroupingService(new NullLogger()),
            MockSettingServiceFactory::getMock(
                $this,
                [
                    SettingKey::LINE_ANNOTATION->value => true
                ]
            ),
            $mockEventBridgeEventService,
            $mockSqsMessageClient
        );

        $this->assertTrue(
            $uploadsFinalisedEventProcessor->process($uploadsFinalised)
        );
    }

    public function testProcessingEventWithBaseComparison(): void
    {
        $uploadsFinalised = new UploadsFinalised(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            parent: ['mock-parent-1'],
            pullRequest: 1,
            baseCommit: 'mock-base-commit',
            baseRef: 'main'
        );

        $mockHeadReport = $this->createMock(ReportInterface::class);
        $mockHeadReport->method('getCoveragePercentage')
            ->willReturn(91.0);

        $mockBaseReport = $this->createMock(ReportInterface::class);
        $mockBaseReport->method('getCoveragePercentage')
            ->willReturn(90.0);

        $reportComparison = new ReportComparison(
            baseReport: $mockBaseReport,
            headReport: $mockHeadReport,
        );

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);
        // The head report should be extracted from the comparison rather than analysed
        // directly
        $mockCoverageAnalyserService->expects($this->never())
            ->method('analyse');

        $mockCoverageComparisonService = $this->createMock(CoverageComparisonService::class);
        $mockCoverageComparisonService->expects($this->once())
            ->method('getSuitableComparisonForWaypoint')
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
            $mockCoverageComparisonService,
            new LineGroupingService(new NullLogger()),
            MockSettingServiceFactory::getMock(
                $this,
                [
                    SettingKey::LINE_ANNOTATION->value => true
                ]
            ),
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
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            parent: []
        );

        $mockReport = $this->createMock(ReportInterface::class);

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->with($this->isInstanceOf(ReportWaypoint::class))
            ->willReturn($mockReport);

        $mockCoverageComparisonService = $this->createMock(CoverageComparisonService::class);
        $mockCoverageComparisonService->expects($this->once())
            ->method('getSuitableComparisonForWaypoint')
            ->willReturn(null);

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
            $mockCoverageComparisonService,
            new LineGroupingService(new NullLogger()),
            MockSettingServiceFactory::getMock(
                $this,
                [
                    SettingKey::LINE_ANNOTATION->value => true
                ]
            ),
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
