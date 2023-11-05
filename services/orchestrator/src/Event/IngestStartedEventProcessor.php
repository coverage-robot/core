<?php

namespace App\Event;

use Packages\Event\Enum\Event;

class IngestStartedEventProcessor extends AbstractIngestEventProcessor
{
    public static function getEvent(): string
    {
        return Event::INGEST_STARTED->value;
    }
}
