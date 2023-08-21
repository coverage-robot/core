<?php

namespace App\Service;

use App\Enum\EnvironmentVariable;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Input\PutEventsRequest;
use AsyncAws\EventBridge\ValueObject\PutEventsRequestEntry;
use JsonException;
use JsonSerializable;
use Packages\Models\Enum\EventBus\CoverageEventSource;
use Packages\Models\Enum\EventBus\CoverageEvent;

class EventBridgeEventService
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
                        'Source' => CoverageEventSource::INGEST->value,
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
