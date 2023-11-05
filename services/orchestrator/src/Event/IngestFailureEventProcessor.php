<?php

namespace App\Event;

use Packages\Event\Enum\Event;

class IngestFailureEventProcessor extends AbstractIngestEventProcessor
{
    public static function getEvent(): string
    {
        return Event::INGEST_FAILURE->value;
    }
}
