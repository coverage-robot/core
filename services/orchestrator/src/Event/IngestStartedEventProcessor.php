<?php

namespace App\Event;

use Override;
use Packages\Contracts\Event\Event;

class IngestStartedEventProcessor extends AbstractIngestEventProcessor
{
    #[Override]
    public static function getEvent(): string
    {
        return Event::INGEST_STARTED->value;
    }
}
