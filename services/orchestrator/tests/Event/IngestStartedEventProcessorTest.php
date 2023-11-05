<?php

namespace App\Tests\Event;

use App\Event\IngestStartedEventProcessor;
use Packages\Event\Enum\Event;
use Packages\Event\Model\IngestStarted;

class IngestStartedEventProcessorTest extends AbstractIngestEventProcessorTestCase
{
    public static function getEventProcessor(): string
    {
        return IngestStartedEventProcessor::class;
    }

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
