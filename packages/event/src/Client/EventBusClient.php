<?php

namespace Packages\Event\Client;

use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Input\PutEventsRequest;
use AsyncAws\EventBridge\ValueObject\PutEventsRequestEntry;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\EventSource;
use Packages\Telemetry\Enum\EnvironmentVariable;
use Packages\Telemetry\Service\TraceContext;
use Symfony\Component\Serializer\SerializerInterface;

class EventBusClient
{
    /**
     * The event bus name which has all of the coverage events published to it.
     *
     * This is dynamic based on the environment the application is running in
     * (i.e. coverage-events-prod, coverage-events-dev, etc).
     */
    private const string EVENT_BUS_NAME = 'coverage-events-%s';

    public function __construct(
        private readonly EventBridgeClient $eventBridgeClient,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @throws HttpException
     */
    public function fireEvent(EventSource $source, EventInterface $event): bool
    {
        $request = [
            'EventBusName' => sprintf(
                self::EVENT_BUS_NAME,
                $this->environmentService->getEnvironment()->value
            ),
            'Source' => $source->value,
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
}
