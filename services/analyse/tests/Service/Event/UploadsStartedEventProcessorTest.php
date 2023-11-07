<?php

namespace App\Tests\Service\Event;

use App\Service\Event\UploadsStartedEventProcessor;
use Packages\Event\Enum\Event;
use PHPUnit\Framework\TestCase;

class UploadsStartedEventProcessorTest extends TestCase
{

    public function testGetEvent(): void
    {
        $this->assertEquals(
            Event::UPLOADS_STARTED->value,
            UploadsStartedEventProcessor::getEvent()
        );
    }

    public function testProcess(): void
    {
    }
}
