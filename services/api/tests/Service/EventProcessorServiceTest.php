<?php

namespace App\Tests\Service;

use Packages\Contracts\Event\Event;
use Packages\Event\Service\EventProcessorService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventProcessorServiceTest extends KernelTestCase
{
    public function testAllProcessorsAvailableToHandleEvents(): void
    {
        /** @var EventProcessorService $eventProcessorService */
        $eventProcessorService = $this->getContainer()
            ->get(EventProcessorService::class);

        $this->assertEquals(
            [
                Event::COVERAGE_FINALISED->value,
            ],
            array_keys($eventProcessorService->getRegisteredProcessors())
        );
    }
}
