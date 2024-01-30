<?php

namespace App\Event;

use Override;
use Packages\Contracts\Event\Event;

final class IngestFailureEventProcessor extends AbstractIngestEventProcessor
{
    #[Override]
    public static function getEvent(): string
    {
        return Event::INGEST_FAILURE->value;
    }
}
