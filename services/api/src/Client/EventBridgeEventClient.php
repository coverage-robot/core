<?php

namespace App\Client;

use App\Enum\EnvironmentVariable;
use App\Service\EnvironmentService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Input\PutEventsRequest;
use AsyncAws\EventBridge\ValueObject\PutEventsRequestEntry;
use JsonException;
use JsonSerializable;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\EventBus\CoverageEventSource;

class EventBridgeEventClient
{
    public function __construct(
        private readonly EventBridgeClient $eventBridgeClient,
        private readonly EnvironmentService $environmentService
    ) {
    }

    /**
     * @throws HttpException
     * @throws JsonException
     */
    public function publishEvent(CoverageEvent $event, JsonSerializable|array $detail): bool
    {
        $events = $this->eventBridgeClient->putEvents(
            new PutEventsRequest([
                'Entries' => [
                    new PutEventsRequestEntry([
                        'EventBusName' => $this->environmentService->getVariable(EnvironmentVariable::EVENT_BUS),
                        'Source' => CoverageEventSource::API->value,
                        'DetailType' => $event->value,
                        'Detail' => json_encode($detail, JSON_THROW_ON_ERROR),
                    ])
                ],
            ])
        );

        $events->resolve();

        return $events->getFailedEntryCount() == 0;
    }
}
