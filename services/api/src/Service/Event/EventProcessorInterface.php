<?php

namespace App\Service\Event;

use Bref\Event\EventBridge\EventBridgeEvent;
use Packages\Event\Enum\Event;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.event_processor')]
interface EventProcessorInterface extends \Packages\Event\Processor\EventProcessorInterface
{
}
