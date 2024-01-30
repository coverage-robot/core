<?php

namespace App\Tests\Service;

use Packages\Contracts\Event\Event;
use Packages\Event\Service\EventProcessorService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EventProcessorServiceTest extends KernelTestCase
{
    public function testAllProcessorsAvailableToHandleEvents(): void
    {
        /** @var EventProcessorService $eventProcessorService */
        $eventProcessorService = $this->getContainer()
            ->get(EventProcessorService::class);

        $this->assertEquals(
            [
                Event::UPLOADS_FINALISED->value,
                Event::UPLOADS_STARTED->value,
            ],
            array_keys($eventProcessorService->getRegisteredProcessors())
        );
    }
}
