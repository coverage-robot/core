<?php

namespace App\Event;

use Packages\Contracts\Event\Event;

class IngestStartedEventProcessor extends AbstractIngestEventProcessor
{
    public static function getEvent(): string
    {
        return Event::INGEST_STARTED->value;
    }
}
