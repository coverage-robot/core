<?php

namespace App\Tests\Handler;

use App\Handler\EventHandler;
use App\Model\PublishableCoverageDataInterface;
use App\Service\CoverageAnalyserService;
use App\Service\EventBridgeEventService;
use App\Service\Publisher\CoveragePublisherService;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use DateTimeImmutable;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EventHandlerTest extends TestCase
{
    public function testHandleEvent(): void
    {
        $body = [
            'uploadId' => 'mock-uuid',
            'provider' => Provider::GITHUB->value,
            'commit' => 'mock-commit',
            'parent' => '["mock-parent-commit"]',
            'owner' => 'mock-owner',
            'tag' => 'mock-tag',
            'ref' => 'mock-ref',
            'repository' => 'mock-repository',
            'ingestTime' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ];

        $upload = Upload::from($body);

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);
        $mockPublishableCoverageData->expects($this->once())
            ->method('getCoveragePercentage')
            ->willReturn(100.0);

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);

        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->with($upload)
            ->willReturn($mockPublishableCoverageData);

        $mockCoveragePublisherService = $this->createMock(CoveragePublisherService::class);

        $mockCoveragePublisherService->expects($this->once())
            ->method('publish')
            ->with($upload, $mockPublishableCoverageData)
            ->willReturn(true);

        $mockEventBridgeEventService = $this->createMock(EventBridgeEventService::class);
        $mockEventBridgeEventService->expects($this->once())
            ->method('publishEvent')
            ->with(CoverageEvent::ANALYSE_SUCCESS, [
                'upload' => $upload->jsonSerialize(),
                'coveragePercentage' => 100.0,
            ]);

        $handler = new EventHandler(
            new NullLogger(),
            $mockCoverageAnalyserService,
            $mockCoveragePublisherService,
            $mockEventBridgeEventService,
            new NullLogger()
        );

        $handler->handleEventBridge(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                    'detail' => $upload->jsonSerialize()
                ]
            ),
            Context::fake()
        );
    }

    public function testHandleFailedPublishEvent(): void
    {
        $body = [
            'uploadId' => 'mock-uuid',
            'provider' => Provider::GITHUB->value,
            'commit' => 'mock-commit',
            'parent' => '["mock-parent-commit"]',
            'owner' => 'mock-owner',
            'tag' => 'mock-tag',
            'ref' => 'mock-ref',
            'repository' => 'mock-repository',
            'ingestTime' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ];

        $upload = Upload::from($body);

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);
        $mockPublishableCoverageData->expects($this->never())
            ->method('getCoveragePercentage');

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);

        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->with($upload)
            ->willReturn($mockPublishableCoverageData);

        $mockCoveragePublisherService = $this->createMock(CoveragePublisherService::class);

        $mockCoveragePublisherService->expects($this->once())
            ->method('publish')
            ->with($upload, $mockPublishableCoverageData)
            ->willReturn(false);

        $mockEventBridgeEventService = $this->createMock(EventBridgeEventService::class);
        $mockEventBridgeEventService->expects($this->once())
            ->method('publishEvent')
            ->with(CoverageEvent::ANALYSE_FAILURE, $upload);

        $handler = new EventHandler(
            new NullLogger(),
            $mockCoverageAnalyserService,
            $mockCoveragePublisherService,
            $mockEventBridgeEventService,
            new NullLogger()
        );

        $handler->handleEventBridge(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                    'detail' => $upload->jsonSerialize()
                ]
            ),
            Context::fake()
        );
    }
}
