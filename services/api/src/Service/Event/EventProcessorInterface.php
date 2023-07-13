<?php

namespace App\Service\Event;

use Bref\Event\EventBridge\EventBridgeEvent;

interface EventProcessorInterface
{
    public function process(EventBridgeEvent $event): void;
}
