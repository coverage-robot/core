<?php

namespace Packages\Event\Processor;

use Packages\Event\Model\EventInterface;

interface EventProcessorInterface
{
    public function process(EventInterface $event): bool;

    public static function getEvent(): string;
}
