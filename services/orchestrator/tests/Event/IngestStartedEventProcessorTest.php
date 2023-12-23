<?php

namespace App\Tests\Event;

use App\Event\IngestStartedEventProcessor;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Event\Model\IngestStarted;

class IngestStartedEventProcessorTest extends AbstractIngestEventProcessorTestCase
{
    #[Override]
    public static function getEventProcessor(): string
    {
        return IngestStartedEventProcessor::class;
    }

    #[Override]
    public static function getEvent(): string
    {
        return IngestStarted::class;
    }

    public function testGetEvent(): void
    {
        $this->assertEquals(
            Event::INGEST_STARTED->value,
            IngestStartedEventProcessor::getEvent()
        );
    }
}
