<?php

declare(strict_types=1);

namespace Packages\Event\Client;

use DateTimeInterface;
use Packages\Contracts\Event\EventInterface;

interface EventBusClientInterface
{
    public function fireEvent(EventInterface $event): bool;

    public function scheduleEvent(
        EventInterface $event,
        DateTimeInterface $fireAt
    ): bool;
}
