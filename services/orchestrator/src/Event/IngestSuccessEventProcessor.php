<?php

namespace App\Event;

use Override;
use Packages\Contracts\Event\Event;

final class IngestSuccessEventProcessor extends AbstractIngestEventProcessor
{
    #[Override]
    public static function getEvent(): string
    {
        return Event::INGEST_SUCCESS->value;
    }
}
