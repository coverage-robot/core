<?php

namespace Packages\Event\Tests\Client;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Input\PutEventsRequest;
use AsyncAws\EventBridge\Result\PutEventsResponse;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\EventSource;
use Packages\Event\Client\EventBusClient;
use Packages\Telemetry\Enum\EnvironmentVariable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

class EventBusClientTest extends TestCase
{
    public function testEventIsFiredWithCorrectProperties(): void
    {
        $mockEvent = $this->createMock(EventInterface::class);
        $mockEvent->method('getType')
            ->willReturn(Event::INGEST_SUCCESS);

        $mockEventBridgeEventClient = $this->createMock(EventBridgeClient::class);

        $mockSerializer = $this->createMock(SerializerInterface::class);
        $mockSerializer->expects($this->once())
            ->method('serialize')
            ->with($mockEvent, 'json')
            ->willReturn('mock-serialized-json');


        $mockEnvironmentService = $this->createMock(EnvironmentServiceInterface::class);
        $mockEnvironmentService->expects($this->once())
            ->method('getEnvironment')
            ->willReturn(Environment::TESTING);
        $mockEnvironmentService->expects($this->exactly(2))
            ->method('getVariable')
            ->with(EnvironmentVariable::X_AMZN_TRACE_ID)
            ->willReturn('mock-trace-id');

        $eventBusClient = new EventBusClient(
            $mockEventBridgeEventClient,
            $mockEnvironmentService,
            $mockSerializer
        );

        $mockEventBridgeEventClient->expects($this->once())
            ->method('putEvents')
            ->with(
                self::callback(function (PutEventsRequest $request) {
                    $event = $request->getEntries()[0];

                    $this->assertEquals(
                        EventSource::ANALYSE->value,
                        $event->getSource()
                    );
                    $this->assertEquals(
                        'mock-trace-id',
                        $event->getTraceHeader()
                    );
                    $this->assertEquals(
                        Event::INGEST_SUCCESS->value,
                        $event->getDetailType()
                    );
                    $this->assertEquals(
                        'mock-serialized-json',
                        $event->getDetail()
                    );

                    return true;
                })
            )
            ->willReturn(
                ResultMockFactory::create(
                    PutEventsResponse::class,
                    [
                        'FailedEntryCount' => 0,
                    ]
                )
            );

        $this->assertTrue(
            $eventBusClient->fireEvent(
                EventSource::ANALYSE,
                $mockEvent
            )
        );
    }
}
