<?php

namespace App\Tests\Handler;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Handler\EventHandler;
use App\Model\PublishableCoverageDataInterface;
use App\Service\CoverageAnalyserService;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use DateTimeImmutable;
use JsonException;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
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
        $mockPublishableCoverageData->expects($this->exactly(3))
            ->method('getCoveragePercentage')
            ->willReturn(100.0);

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);

        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->with($upload)
            ->willReturn($mockPublishableCoverageData);

        $mockSqsEventClient = $this->createMock(SqsMessageClient::class);

        $mockSqsEventClient->expects($this->once())
            ->method('queuePublishableMessage')
            ->willReturn(true);

        $mockEventBridgeEventService = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventService->expects($this->once())
            ->method('publishEvent')
            ->with(CoverageEvent::ANALYSE_SUCCESS, [
                'upload' => $upload->jsonSerialize(),
                'coveragePercentage' => 100.0,
            ]);

        $handler = new EventHandler(
            new NullLogger(),
            $mockCoverageAnalyserService,
            $mockSqsEventClient,
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

    public function testHandleEventInvalidJson(): void
    {
        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);

        $mockCoverageAnalyserService->expects($this->never())
            ->method('analyse');

        $mockSqsEventClient = $this->createMock(SqsMessageClient::class);

        $mockSqsEventClient->expects($this->never())
            ->method('queuePublishableMessage');

        $mockEventBridgeEventService = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventService->expects($this->never())
            ->method('publishEvent');

        $handler = new EventHandler(
            new NullLogger(),
            $mockCoverageAnalyserService,
            $mockSqsEventClient,
            $mockEventBridgeEventService,
            new NullLogger()
        );

        $mockEvent = $this->createMock(EventBridgeEvent::class);
        $mockEvent->expects($this->once())
            ->method('getDetail')
            ->willThrowException(new JsonException('Invalid JSON'));

        $handler->handleEventBridge(
            $mockEvent,
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
        $mockPublishableCoverageData->expects($this->exactly(2))
            ->method('getCoveragePercentage');

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);

        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->with($upload)
            ->willReturn($mockPublishableCoverageData);

        $mockSqsEventClient = $this->createMock(SqsMessageClient::class);

        $mockSqsEventClient->expects($this->once())
            ->method('queuePublishableMessage')
            ->willReturn(false);

        $mockEventBridgeEventService = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventService->expects($this->once())
            ->method('publishEvent')
            ->with(CoverageEvent::ANALYSE_FAILURE, $upload);

        $handler = new EventHandler(
            new NullLogger(),
            $mockCoverageAnalyserService,
            $mockSqsEventClient,
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
