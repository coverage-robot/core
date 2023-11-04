<?php

namespace App\Tests\Handler;

use App\Handler\EventHandler;
use App\Service\Event\EventProcessor;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Packages\Event\Enum\Event;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EventHandlerTest extends TestCase
{
    public function testHandleEventBridge(): void
    {
        $event = new EventBridgeEvent([
            'detail-type' => Event::COVERAGE_FINALISED->value,
            'detail' => ''
        ]);

        $mockProcessor = $this->createMock(EventProcessor::class);
        $mockProcessor->expects($this->once())
            ->method('process')
            ->with($event);

        $eventHandler = new EventHandler(
            new NullLogger(),
            $mockProcessor
        );

        $eventHandler->handleEventBridge($event, Context::fake());
    }
}
