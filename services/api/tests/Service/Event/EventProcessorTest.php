<?php

namespace App\Tests\Service\Event;

use App\Service\Event\CoverageFinalisedEventProcessor;
use App\Service\Event\EventProcessor;
use Bref\Event\EventBridge\EventBridgeEvent;
use Packages\Models\Enum\EventBus\CoverageEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EventProcessorTest extends TestCase
{
    #[DataProvider('eventsDataProvider')]
    public function testHandleEventBridge(string $event, bool $shouldHandle): void
    {
        $event = new EventBridgeEvent([
            'detail-type' => $event,
            'detail' => ''
        ]);

        $mockProcessor = $this->createMock(CoverageFinalisedEventProcessor::class);

        if (!$shouldHandle) {
            $this->expectException(RuntimeException::class);
        } else {
            $mockProcessor->expects($this->once())
                ->method('process')
                ->with($event);
        }

        $eventProcessor = new EventProcessor(
            [
                CoverageEvent::COVERAGE_FINALISED->value => $mockProcessor
            ]
        );

        $eventProcessor->process($event);
    }

    public static function eventsDataProvider(): array
    {
        return array_map(
            static fn(CoverageEvent $event) => [
                $event->value,
                $event === CoverageEvent::COVERAGE_FINALISED
            ],
            CoverageEvent::cases()
        );
    }
}
