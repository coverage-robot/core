<?php

namespace App\Tests\Event;

use App\Event\IngestSuccessEventProcessor;
use Packages\Event\Enum\Event;
use Packages\Event\Model\IngestSuccess;

class IngestSuccessEventProcessorTest extends AbstractIngestEventProcessorTestCase
{
    public static function getEventProcessor(): string
    {
        return IngestSuccessEventProcessor::class;
    }

    public static function getEvent(): string
    {
        return IngestSuccess::class;
    }

    public function testGetEvent(): void
    {
        $this->assertEquals(
            Event::INGEST_SUCCESS->value,
            IngestSuccessEventProcessor::getEvent()
        );
    }
}
