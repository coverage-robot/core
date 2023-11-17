<?php

namespace Packages\Event\Processor;

use Packages\Contracts\Event\EventInterface;

interface EventProcessorInterface
{
    /**
     * Process an incoming event of a specific type.
     */
    public function process(EventInterface $event): bool;

    /**
     * The event type that this processor handles.
     */
    public static function getEvent(): string;
}
