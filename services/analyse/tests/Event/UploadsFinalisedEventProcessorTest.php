<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Event\UploadsFinalisedEventProcessor;
use App\Model\CoverageReport;
use App\Model\CoverageReportComparison;
use App\Model\ReportWaypoint;
use App\Query\Result\QueryResultIterator;
use App\Query\Result\TotalUploadsQueryResult;
use App\Service\CoverageAnalyserServiceInterface;
use App\Service\CoverageComparisonServiceInterface;
use App\Service\LineGroupingService;
use ArrayIterator;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Mock\MockSettingServiceFactory;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Client\EventBusClientInterface;
use Packages\Event\Model\CoverageFailed;
use Packages\Event\Model\UploadsFinalised;
use Packages\Message\Client\SqsClientInterface;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableMessageCollection;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Packages\Telemetry\Service\MetricServiceInterface;
use Psr\Log\NullLogger;
use RuntimeException;
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
            projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
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
            projectId: 'mock-project',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: [],
            diff: []
        );
        $baseWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: 'mock-project',
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
                0,
                0.0,
                new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                0.0,
                new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                10,
                new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
            headReport: new CoverageReport(
                $headWaypoint,
                new TotalUploadsQueryResult([], [], []),
                0,
                0,
                0,
                0,
                1.0,
                new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                0.0,
                new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                ),
                10,
                new QueryResultIterator(
                    new ArrayIterator([]),
                    0,
                    static fn(): never => throw new RuntimeException('Should never be called')
                )
            ),
        );

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserServiceInterface::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('getWaypointFromEvent')
            ->with($uploadsFinalised)
            ->willReturn($headWaypoint);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->willReturn($reportComparison->getHeadReport());

        $mockCoverageComparisonService = $this->createMock(CoverageComparisonServiceInterface::class);
        $mockCoverageComparisonService->expects($this->once())
            ->method('getComparisonForCoverageReport')
            ->with($reportComparison->getHeadReport(), $uploadsFinalised)
            ->willReturn($reportComparison);

        $mockEventBusClient = $this->createMock(EventBusClientInterface::class);

        // Should fire once when finalising coverage
        $mockEventBusClient->expects($this->once())
            ->method('fireEvent')
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
                        $this->assertCount(
                            3,
                            $message
                        );
                        $this->assertInstanceOf(
                            PublishablePullRequestMessage::class,
                            $message->getMessages()[0]
                        );
                        $this->assertInstanceOf(
                            PublishableCheckRunMessage::class,
                            $message->getMessages()[1]
                        );
                        $this->assertEqualsWithDelta(
                            1.0,
                            $message->getMessages()[1]
                                ->getCoverageChange(),
                            PHP_FLOAT_EPSILON
                        );
                        return true;
                    }
                )
            )
            ->willReturn(true);

        $uploadsFinalisedEventProcessor = new UploadsFinalisedEventProcessor(
            new NullLogger(),
            $this->createStub(MetricServiceInterface::class),
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
            projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            parent: []
        );

        $headWaypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: 'mock-project',
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
            0,
            1.0,
            new QueryResultIterator(
                new ArrayIterator([]),
                0,
                static fn(): never => throw new RuntimeException('Should never be called')
            ),
            new QueryResultIterator(
                new ArrayIterator([]),
                0,
                static fn(): never => throw new RuntimeException('Should never be called')
            ),
            0.0,
            new QueryResultIterator(
                new ArrayIterator([]),
                0,
                static fn(): never => throw new RuntimeException('Should never be called')
            ),
            10,
            new QueryResultIterator(
                new ArrayIterator([]),
                0,
                static fn(): never => throw new RuntimeException('Should never be called')
            )
        );

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserServiceInterface::class);
        $mockCoverageAnalyserService->expects($this->once())
            ->method('getWaypointFromEvent')
            ->with($uploadsFinalised)
            ->willReturn($headWaypoint);
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
            ->with($this->isInstanceOf(CoverageFailed::class))
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
                        $this->assertCount(
                            3,
                            $message
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
            $this->createStub(MetricServiceInterface::class),
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
