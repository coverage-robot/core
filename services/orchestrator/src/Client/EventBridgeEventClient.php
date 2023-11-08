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
        $events = $this->eventBridgeClient->putEvents(
            new PutEventsRequest([
                'Entries' => [
                    new PutEventsRequestEntry([
                        'EventBusName' => $this->environmentService->getVariable(EnvironmentVariable::EVENT_BUS),
                        'Source' => EventSource::ORCHESTRATOR->value,
                        'DetailType' => $event->getType()->value,
                        'Detail' => $this->serializer->serialize($event, 'json')
                    ])
                ],
            ])
        );

        $events->resolve();

        return $events->getFailedEntryCount() == 0;
    }
}
