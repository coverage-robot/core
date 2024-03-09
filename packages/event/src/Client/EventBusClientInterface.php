<?php

namespace Packages\Event\Client;

use DateTimeInterface;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\EventSource;

interface EventBusClientInterface
{
    public function fireEvent(EventSource $source, EventInterface $event): bool;

    public function scheduleEvent(
        EventSource $source,
        EventInterface $event,
        DateTimeInterface $fireAt
    ): bool;
}
