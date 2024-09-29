<?php

namespace Packages\Event\Client;

use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Input\PutEventsRequest;
use AsyncAws\EventBridge\ValueObject\PutEventsRequestEntry;
use AsyncAws\Scheduler\Enum\ActionAfterCompletion;
use AsyncAws\Scheduler\Enum\FlexibleTimeWindowMode;
use AsyncAws\Scheduler\Enum\ScheduleState;
use AsyncAws\Scheduler\Input\CreateScheduleInput;
use AsyncAws\Scheduler\SchedulerClient;
use AsyncAws\Scheduler\ValueObject\EventBridgeParameters;
use AsyncAws\Scheduler\ValueObject\FlexibleTimeWindow;
use AsyncAws\Scheduler\ValueObject\Target;
use DateTimeInterface;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Exception\InvalidEventException;
use Packages\Event\Service\EventValidationService;
use Packages\Telemetry\Enum\EnvironmentVariable;
use Packages\Telemetry\Service\TraceContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;

final class EventBusClient implements EventBusClientInterface
{
    /**
     * The event bus name which has all of the coverage events published to it.
     *
     * This is dynamic based on the environment the application is running in
     * (i.e. coverage-events-prod, coverage-events-dev, etc).
     */
    public const string EVENT_BUS_NAME = 'coverage-events-%kernel.environment%';

    /**
     * The ARN for the Event Bus.
     */
    public const string EVENT_BUS_ARN =
        'arn:aws:events:%event_bus.region%:%event_bus.account_id%:event-bus/%event_bus.name%';

    /**
     * The name of the role EventBridge will use when firing a scheduled event on the event
     * bus.
     */
    public const string EVENT_SCHEDULER_ROLE = 'coverage-events-scheduler-role-%kernel.environment%';

    /**
     * The ARN for the role EventBridge will use when firing a scheduled event.
     */
    public const string EVENT_SCHEDULER_ROLE_ARN =
        'arn:aws:iam::%event_bus.account_id%:role/%event_bus.scheduler_role%';

    public function __construct(
        #[Autowire(value: '%event_bus.name%')]
        private readonly string $eventBusName,
        #[Autowire(value: '%event_bus.event_bus_arn%')]
        private readonly string $eventBusArn,
        #[Autowire(value: '%event_bus.scheduler_role_arn%')]
        private readonly string $schedulerRoleArn,
        private readonly EventBridgeClient $eventBridgeClient,
        private readonly SchedulerClient $schedulerClient,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly SerializerInterface $serializer,
        private readonly EventValidationService $eventValidationService,
        private readonly LoggerInterface $eventBusClientLogger
    ) {
    }

    /**
     * @throws HttpException
     */
    #[Override]
    public function fireEvent(EventInterface $event): bool
    {
        try {
            $this->eventValidationService->validate($event);
        } catch (InvalidEventException $invalidEventException) {
            $this->eventBusClientLogger->error(
                sprintf(
                    'Unable to dispatch %s as it failed validation.',
                    (string)$event
                ),
                [
                    'exception' => $invalidEventException,
                    'event' => $event
                ]
            );

            return false;
        }

        $request = [
            'EventBusName' => $this->eventBusName,
            'Source' => $this->environmentService->getService()->value,
            'DetailType' => $event->getType()->value,
            'Detail' => $this->serializer->serialize($event, 'json'),
        ];

        if ($this->environmentService->getVariable(EnvironmentVariable::X_AMZN_TRACE_ID) !== '') {
            /**
             * The trace header will be propagated to the next service in the chain if provided
             * from a previous request.
             *
             * This value is propagated into the environment in a number of methods. But in the
             * Event Bus context that's handled by a trait.
             *
             * @see TraceContext
             */
            $request['TraceHeader'] = $this->environmentService->getVariable(EnvironmentVariable::X_AMZN_TRACE_ID);
        }

        $events = $this->eventBridgeClient->putEvents(
            new PutEventsRequest([
                'Entries' => [
                    new PutEventsRequestEntry($request)
                ],
            ])
        );

        $events->resolve();

        return $events->getFailedEntryCount() == 0;
    }

    /**
     * Schedule a one-off event to be fired at a specific time.
     *
     * @throws HttpException
     */
    #[Override]
    public function scheduleEvent(
        EventInterface $event,
        DateTimeInterface $fireAt
    ): bool {
        try {
            $this->eventValidationService->validate($event);
        } catch (InvalidEventException $invalidEventException) {
            $this->eventBusClientLogger->error(
                sprintf(
                    'Unable to schedule %s as it failed validation.',
                    (string)$event
                ),
                [
                    'exception' => $invalidEventException,
                    'event' => $event
                ]
            );

            return false;
        }

        $schedule = $this->schedulerClient->createSchedule(
            new CreateScheduleInput([
                'ActionAfterCompletion' => ActionAfterCompletion::DELETE,
                'Target' => new Target([
                    'Arn' => $this->eventBusArn,
                    'RoleArn' => $this->schedulerRoleArn,
                    'EventBridgeParameters' => new EventBridgeParameters([
                        'Source' => $this->environmentService->getService()->value,
                        'DetailType' => $event->getType()->value,
                    ]),
                    'Input' => $this->serializer->serialize($event, 'json')
                ]),
                'Name' => sprintf(
                    '%s-%s',
                    $event->getType()->value,
                    $event->getEventTime()->getTimestamp()
                ),
                'State' => ScheduleState::ENABLED,
                'FlexibleTimeWindow' => new FlexibleTimeWindow([
                    'Mode' => FlexibleTimeWindowMode::OFF
                ]),
                'ScheduleExpression' => sprintf(
                    'at(%s)',
                    $fireAt->format('Y-m-d\TH:i:s')
                ),
                'ScheduleExpressionTimezone' => $fireAt->format('e')
            ])
        );

        $schedule->resolve();

        return true;
    }
}
