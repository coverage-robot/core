<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Service\EnvironmentService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Input\PutEventsRequest;
use AsyncAws\EventBridge\ValueObject\PutEventsRequestEntry;
use JsonException;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\EventBus\CoverageEventSource;
use Packages\Models\Model\Event\EventInterface;
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
     * @throws JsonException
     */
    public function publishEvent(CoverageEvent $event, EventInterface|array $detail): bool
    {
        $events = $this->eventBridgeClient->putEvents(
            new PutEventsRequest([
                'Entries' => [
                    new PutEventsRequestEntry([
                        'EventBusName' => $this->environmentService->getVariable(EnvironmentVariable::EVENT_BUS),
                        'Source' => CoverageEventSource::ANALYSE->value,
                        'DetailType' => $event->value,
                        'Detail' => $this->serializer->serialize($detail, 'json')
                    ])
                ],
            ])
        );

        $events->resolve();

        return $events->getFailedEntryCount() == 0;
    }
}
