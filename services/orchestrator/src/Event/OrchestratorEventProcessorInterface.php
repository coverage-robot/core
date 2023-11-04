<?php

namespace App\Event;

use Packages\Event\Processor\EventProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.orchestrator_event_processor')]
interface OrchestratorEventProcessorInterface extends EventProcessorInterface
{
}
