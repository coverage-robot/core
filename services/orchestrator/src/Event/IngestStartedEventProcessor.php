<?php

declare(strict_types=1);

namespace App\Event;

use Override;
use Packages\Contracts\Event\Event;

final class IngestStartedEventProcessor extends AbstractIngestEventProcessor
{
    #[Override]
    public static function getEvent(): string
    {
        return Event::INGEST_STARTED->value;
    }
}
