<?php

namespace App\Service\Event;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.event_processor')]
interface EventProcessorInterface extends \Packages\Event\Processor\EventProcessorInterface
{
}
