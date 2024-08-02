<?php

namespace App\Tests\Event;

use App\Event\UploadsFinalisedEventProcessor;
use App\Model\CarryforwardTag;
use App\Model\CoverageReport;
use App\Model\CoverageReportComparison;
use App\Model\ReportWaypoint;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Service\CoverageAnalyserServiceInterface;
use App\Service\CoverageComparisonServiceInterface;
use App\Service\LineGroupingService;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Mock\MockSettingServiceFactory;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventSource;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Client\EventBusClientInterface;
use Packages\Event\Model\AnalyseFailure;
use Packages\Event\Model\CoverageFailed;
use Packages\Event\Model\CoverageFinalised;
use Packages\Event\Model\UploadsFinalised;
use Packages\Message\Client\SqsClientInterface;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableMessageCollection;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Packages\Telemetry\Service\MetricServiceInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class UploadsFinalisedEventProcessorTest extends KernelTestCase
{
    public function testGetEvent(): void
    {
        $this->assertEquals(
            Event::UPLOADS_FINALISED->value,
            UploadsFinalisedEventProcessor::getEvent()
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

        $headWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: []
        );
        $baseWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: []
        );

        $reportComparison = new CoverageReportComparison(
            baseReport: new CoverageReport(
                $baseWaypoint,
                new TotalUploadsQueryResult([], [], []),
                0,
                0,
                0,
                0.0,
                new TagCoverageCollectionQueryResult([]),
                0.0,
                new FileCoverageCollectionQueryResult([]),
                10,
                new LineCoverageCollectionQueryResult([])
            ),
            headReport: new CoverageReport(
                $headWaypoint,
                new TotalUploadsQueryResult([], [], []),
                0,
                0,
                0,
                1.0,
                new TagCoverageCollectionQueryResult([]),
                0.0,
                new FileCoverageCollectionQueryResult([]),
                10,
                new LineCoverageCollectionQueryResult([])
            ),
        );

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserServiceInterface::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('getWaypointFromEvent')
            ->with($uploadsFinalised)
            ->willReturn($headWaypoint);
        $mockCoverageAnalyserService->expects($this->exactly(2))
            ->method('getCarryforwardTags')
            ->willReturnMap([
                [$headWaypoint, [new CarryforwardTag('', '', [100], [])]],
                [$baseWaypoint, [new CarryforwardTag('', '', [101], [])]],
            ]);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->willReturn($reportComparison->getHeadReport());

        $mockCoverageComparisonService = $this->createMock(CoverageComparisonServiceInterface::class);
        $mockCoverageComparisonService->expects($this->once())
            ->method('getComparisonForCoverageReport')
            ->with($reportComparison->getHeadReport(), $uploadsFinalised)
            ->willReturn($reportComparison);

        $mockEventBusClient = $this->createMock(EventBusClientInterface::class);
        $mockEventBusClient->expects($this->once())
            ->method('fireEvent')
            ->with(
                EventSource::ANALYSE,
                $this->isInstanceOf(CoverageFinalised::class)
            )
            ->willReturn(true);

        $mockPublishClient = $this->createMock(SqsClientInterface::class);
        $mockPublishClient->expects($this->once())
            ->method('dispatch')
            ->with(
                self::callback(
                    function (PublishableMessageCollection $message) use ($uploadsFinalised): bool {
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
            $this->createMock(MetricServiceInterface::class),
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageAnalyserService,
            $mockCoverageComparisonService,
            new LineGroupingService(new NullLogger()),
            MockSettingServiceFactory::createMock(
                [
                    SettingKey::LINE_COMMENT_TYPE->value => true
                ]
            ),
            $mockEventBusClient,
            $mockPublishClient
        );

        $this->assertTrue($uploadsFinalisedEventProcessor->process($uploadsFinalised));
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

        $headWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: []
        );
        $mockReport = new CoverageReport(
            $headWaypoint,
            new TotalUploadsQueryResult([], [], []),
            0,
            0,
            0,
            1.0,
            new TagCoverageCollectionQueryResult([]),
            0.0,
            new FileCoverageCollectionQueryResult([]),
            10,
            new LineCoverageCollectionQueryResult([])
        );

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserServiceInterface::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('getWaypointFromEvent')
            ->with($uploadsFinalised)
            ->willReturn($headWaypoint);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('getCarryforwardTags')
            ->willReturnMap([
                [$headWaypoint, [new CarryforwardTag('', '', [100], [])]]
            ]);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->with($headWaypoint)
            ->willReturn($mockReport);

        $mockCoverageComparisonService = $this->createMock(CoverageComparisonServiceInterface::class);
        $mockCoverageComparisonService->expects($this->once())
            ->method('getComparisonForCoverageReport')
            ->willReturn(null);

        $mockEventBusClient = $this->createMock(EventBusClientInterface::class);
        $mockEventBusClient->expects($this->once())
            ->method('fireEvent')
            ->with(
                EventSource::ANALYSE,
                $this->isInstanceOf(CoverageFailed::class)
            )
            ->willReturn(true);

        $mockPublishClient = $this->createMock(SqsClientInterface::class);
        $mockPublishClient->expects($this->once())
            ->method('dispatch')
            ->with(
                self::callback(
                    function (PublishableMessageCollection $message) use ($uploadsFinalised): bool {
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
            $this->createMock(MetricServiceInterface::class),
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageAnalyserService,
            $mockCoverageComparisonService,
            new LineGroupingService(new NullLogger()),
            MockSettingServiceFactory::createMock(
                [
                    SettingKey::LINE_COMMENT_TYPE->value => true
                ]
            ),
            $mockEventBusClient,
            $mockPublishClient
        );

        $this->assertFalse($uploadsFinalisedEventProcessor->process($uploadsFinalised));
    }
}
