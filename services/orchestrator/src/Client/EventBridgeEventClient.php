<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Service\EnvironmentService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Input\PutEventsRequest;
use AsyncAws\EventBridge\ValueObject\PutEventsRequestEntry;
use Packages\Event\Enum\EventSource;
use Packages\Event\Model\EventInterface;
use Symfony\Component\Serializer\SerializerInterface;

class EventBridgeEventClient
{
    public function __construct(
        private readonly EventBridgeClient $eventBridgeClient,
        private readonly EnvironmentService $environmentService,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @throws HttpException
     */
    public function publishEvent(EventInterface $event): bool
    {
        $request = [
            'EventBusName' => $this->environmentService->getVariable(EnvironmentVariable::EVENT_BUS),
            'Source' => EventSource::ORCHESTRATOR->value,
            'DetailType' => $event->getType()->value,
            'Detail' => $this->serializer->serialize($event, 'json'),
        ];

        if ($this->environmentService->getVariable(EnvironmentVariable::TRACE_ID)) {
            /**
             * The trace header will be propagated to the next service in the chain if provided
             * from a previous request.
             *
             * This value is propagated into the environment in a number of methods. But in the
             * Event Bus context that's handled by a trait.
             *
             * @see TraceContext
             */
            $request['TraceHeader'] = $this->environmentService->getVariable(EnvironmentVariable::TRACE_ID);
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
