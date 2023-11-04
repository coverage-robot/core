<?php

namespace App\Event;

use Packages\Event\Enum\Event;
use Packages\Event\Model\EventInterface;

class JobStateChangeOrchestratorEventProcessor implements OrchestratorEventProcessorInterface
{

    public function process(EventInterface $event): bool
    {
        return true;
    }

    public static function getEvent(): string
    {
        return Event::JOB_STATE_CHANGE->value;
    }
}