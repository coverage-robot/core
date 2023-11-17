<?php

namespace App\Event;

use Packages\Contracts\Event\Event;

class IngestSuccessEventProcessor extends AbstractIngestEventProcessor
{
    public static function getEvent(): string
    {
        return Event::INGEST_SUCCESS->value;
    }
}
