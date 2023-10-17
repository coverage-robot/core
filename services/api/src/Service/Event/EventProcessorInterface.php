<?php

namespace App\Service\Event;

use Bref\Event\EventBridge\EventBridgeEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.event_processor')]
interface EventProcessorInterface
{
    /**
     * Process the incoming event from the event bus.
     */
    public function process(EventBridgeEvent $event): void;

    /**
     * The type of event being listened to by the processor.
     */
    public static function getProcessorEvent(): string;
}
