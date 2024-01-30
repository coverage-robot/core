<?php

namespace Packages\Event\Client;

use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\EventSource;

interface EventBusClientInterface
{
    public function fireEvent(EventSource $source, EventInterface $event): bool;
}
