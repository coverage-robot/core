<?php

namespace Packages\Event\Tests\Client;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Input\PutEventsRequest;
use AsyncAws\EventBridge\Result\PutEventsResponse;
use AsyncAws\Scheduler\Enum\ActionAfterCompletion;
use AsyncAws\Scheduler\Enum\FlexibleTimeWindowMode;
use AsyncAws\Scheduler\Input\CreateScheduleInput;
use AsyncAws\Scheduler\Result\CreateScheduleOutput;
use AsyncAws\Scheduler\SchedulerClient;
use DateTimeImmutable;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\EventSource;
use Packages\Event\Client\EventBusClient;
use Packages\Event\Service\EventValidationService;
use Packages\Telemetry\Enum\EnvironmentVariable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class EventBusClientTest extends TestCase
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
        $mockEnvironmentService->expects($this->exactly(2))
            ->method('getVariable')
            ->with(EnvironmentVariable::X_AMZN_TRACE_ID)
            ->willReturn('mock-trace-id');

        $mockValidator = $this->createMock(ValidatorInterface::class);
        $mockValidator->expects($this->once())
            ->method('validate')
            ->with($mockEvent);

        $eventBusClient = new EventBusClient(
            'mock-event-bus',
            'mock-event-bus-arn',
            'mock-scheduler-role-arn',
            $mockEventBridgeEventClient,
            $this->createMock(SchedulerClient::class),
            $mockEnvironmentService,
            $mockSerializer,
            new EventValidationService($mockValidator),
            new NullLogger()
        );

        $mockEventBridgeEventClient->expects($this->once())
            ->method('putEvents')
            ->with(
                self::callback(function (PutEventsRequest $request): bool {
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
    public function testEventCanBeScheduled(): void
    {
        $fireAt = new DateTimeImmutable('2055-01-01 00:00:00');

        $mockEvent = $this->createMock(EventInterface::class);
        $mockEvent->method('getType')
            ->willReturn(Event::INGEST_SUCCESS);

        $mockSerializer = $this->createMock(SerializerInterface::class);
        $mockSerializer->expects($this->once())
            ->method('serialize')
            ->with($mockEvent, 'json')
            ->willReturn('mock-serialized-json');

        $mockScheduler = $this->createMock(SchedulerClient::class);
        $mockScheduler->expects($this->once())
            ->method('createSchedule')
            ->with(
                $this->callback(function (CreateScheduleInput $input): bool {
                    $this->assertEquals(
                        FlexibleTimeWindowMode::OFF,
                        $input->getFlexibleTimeWindow()->getMode()
                    );
                    $this->assertEquals(
                        'at(2055-01-01T00:00:00)',
                        $input->getScheduleExpression()
                    );
                    $this->assertEquals(
                        ActionAfterCompletion::DELETE,
                        $input->getActionAfterCompletion()
                    );
                    $this->assertEquals(
                        'mock-scheduler-role-arn',
                        $input->getTarget()->getRoleArn()
                    );
                    $this->assertEquals(
                        'mock-event-bus-arn',
                        $input->getTarget()->getArn()
                    );
                    $this->assertEquals(
                        'mock-serialized-json',
                        $input->getTarget()->getInput()
                    );

                    return true;
                })
            )
            ->willReturn(
                ResultMockFactory::create(
                    CreateScheduleOutput::class
                )
            );

        $mockValidator = $this->createMock(ValidatorInterface::class);
        $mockValidator->expects($this->once())
            ->method('validate')
            ->with($mockEvent);

        $eventBusClient = new EventBusClient(
            'mock-event-bus',
            'mock-event-bus-arn',
            'mock-scheduler-role-arn',
            $this->createMock(EventBridgeClient::class),
            $mockScheduler,
            $this->createMock(EnvironmentServiceInterface::class),
            $mockSerializer,
            new EventValidationService($mockValidator),
            new NullLogger()
        );

        $this->assertTrue(
            $eventBusClient->scheduleEvent(
                EventSource::ANALYSE,
                $mockEvent,
                $fireAt
            )
        );
    }
}
