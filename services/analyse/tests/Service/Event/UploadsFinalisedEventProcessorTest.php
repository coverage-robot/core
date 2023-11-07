<?php

namespace App\Tests\Service\Event;

use App\Service\Event\UploadsFinalisedEventProcessor;
use Packages\Event\Enum\Event;
use PHPUnit\Framework\TestCase;

class UploadsFinalisedEventProcessorTest extends TestCase
{

    public function testGetEvent(): void
    {
        $this->assertEquals(
            Event::UPLOADS_FINALISED->value,
            UploadsFinalisedEventProcessor::getEvent()
        );
    }

    public function testProcess(): void
    {
    }
}
