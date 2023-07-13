<?php

namespace App\Tests\Handler;

use App\Handler\EventHandler;
use App\Service\Event\EventProcessorInterface;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Packages\Models\Enum\EventBus\CoverageEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

class EventHandlerTest extends TestCase
{
    #[DataProvider('eventsDataProvider')]
    public function testHandleEventBridge(string $event, bool $shouldHandle): void
    {
        $event = new EventBridgeEvent([
            'detail-type' => $event,
            'detail' => ""
        ]);

        $container = $this->createMock(ContainerInterface::class);

        $mockProcessor = $this->createMock(EventProcessorInterface::class);

        $eventHandler = new EventHandler(new NullLogger(), $container);

        $container->expects($this->exactly($shouldHandle ? 1 : 0))
            ->method('get')
            ->willReturn($mockProcessor);

        $mockProcessor->expects($this->exactly($shouldHandle ? 1 : 0))
            ->method('process')
            ->with($event);

        $eventHandler->handleEventBridge($event, Context::fake());
    }

    public static function eventsDataProvider(): array
    {
        return array_map(static fn (CoverageEvent $event) => [$event->value, $event === CoverageEvent::INGEST_SUCCESS], CoverageEvent::cases());
    }
}
