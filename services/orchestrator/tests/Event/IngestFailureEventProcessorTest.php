<?php

namespace App\Tests\Event;

use App\Event\IngestFailureEventProcessor;
use Packages\Event\Enum\Event;
use Packages\Event\Model\IngestFailure;

class IngestFailureEventProcessorTest extends AbstractIngestEventProcessorTestCase
{
    public static function getEventProcessor(): string
    {
        return IngestFailureEventProcessor::class;
    }

    public static function getEvent(): string
    {
        return IngestFailure::class;
    }

    public function testGetEvent(): void
    {
        $this->assertEquals(
            Event::INGEST_FAILURE->value,
            IngestFailureEventProcessor::getEvent()
        );
    }
}
