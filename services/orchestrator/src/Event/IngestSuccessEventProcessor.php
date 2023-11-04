<?php

namespace App\Event;

use Packages\Event\Enum\Event;

class IngestSuccessEventProcessor extends AbstractIngestEventProcessor
{
    public static function getEvent(): string
    {
        return Event::INGEST_SUCCESS->value;
    }
}
