<?php

namespace App\Event;

use Packages\Event\Model\EventInterface;

abstract class AbstractIngestEventProcessor implements OrchestratorEventProcessorInterface
{
    public function process(EventInterface $event): bool
    {
        return true;
    }
}
