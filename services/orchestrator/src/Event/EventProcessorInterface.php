<?php

namespace App\Event;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Event processors which listen for events relevant to the orchestrator, and store them
 * in the event store.
 */
#[AutoconfigureTag('app.orchestrator_event_processor')]
interface EventProcessorInterface extends \Packages\Event\Processor\EventProcessorInterface
{
}
