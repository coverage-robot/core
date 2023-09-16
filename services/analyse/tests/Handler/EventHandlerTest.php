<?php

namespace App\Tests\Handler;

use App\Handler\EventHandler;
use App\Service\Event\IngestSuccessEventProcessor;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use DateTimeImmutable;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\PipelineComplete;
use Packages\Models\Model\Event\Upload;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

class EventHandlerTest extends TestCase
{
    public function testHandleUploadEvent(): void
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

        $mockContainer = $this->createMock(ContainerInterface::class);

        $mockProcessor = $this->createMock(IngestSuccessEventProcessor::class);

        $mockProcessor->expects($this->once())
            ->method('process');

        $mockContainer->expects($this->once())
            ->method('get')
            ->with(IngestSuccessEventProcessor::class)
            ->willReturn($mockProcessor);

        $handler = new EventHandler(
            new NullLogger(),
            $mockContainer
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

    public function testHandlePipelineCompleteEvent(): void
    {
        $body = [
            'provider' => Provider::GITHUB->value,
            'commit' => 'mock-commit',
            'owner' => 'mock-owner',
            'ref' => 'mock-ref',
            'repository' => 'mock-repository',
            'completedAt' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ];

        $upload = PipelineComplete::from($body);

        $mockContainer = $this->createMock(ContainerInterface::class);

        $mockProcessor = $this->createMock(IngestSuccessEventProcessor::class);

        $mockProcessor->expects($this->once())
            ->method('process');

        $mockContainer->expects($this->once())
            ->method('get')
            ->with(IngestSuccessEventProcessor::class)
            ->willReturn($mockProcessor);

        $handler = new EventHandler(
            new NullLogger(),
            $mockContainer
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
