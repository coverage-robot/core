<?php

namespace Packages\Event\Service;

use Packages\Event\Enum\Event;
use Packages\Event\Model\EventInterface;

interface EventProcessorServiceInterface
{
    /**
     * Process an event, of a particular type, using the appropriate processor.
     */
    public function process(Event $eventType, EventInterface $event): bool;
}