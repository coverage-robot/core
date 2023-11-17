<?php

namespace Packages\Event\Service;

use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;

interface EventProcessorServiceInterface
{
    /**
     * Process an event, of a particular type, using the appropriate processor.
     */
    public function process(Event $eventType, EventInterface $event): bool;
}