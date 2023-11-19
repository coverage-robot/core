<?php

namespace App\Event;

use Packages\Contracts\Event\Event;

class IngestFailureEventProcessor extends AbstractIngestEventProcessor
{
    public static function getEvent(): string
    {
        return Event::INGEST_FAILURE->value;
    }
}
