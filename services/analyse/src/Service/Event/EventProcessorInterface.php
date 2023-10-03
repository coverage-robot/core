<?php

namespace App\Service\Event;

use Bref\Event\EventBridge\EventBridgeEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.event_processor')]
interface EventProcessorInterface
{
    public function process(EventBridgeEvent $event): void;

    public static function getProcessorEvent(): string;
}
