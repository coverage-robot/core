<?php

namespace App\Tests\Service;

use Packages\Clients\Client\Github\GithubAppClient;
use Packages\Contracts\Event\Event;
use Packages\Event\Service\EventProcessorService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventProcessorServiceTest extends KernelTestCase
{
    public function testAllProcessorsAvailableToHandleEvents(): void
    {
        // Mock out the GitHub client so that we don't need to worry about private key
        // authentication files
        $this->getContainer()
            ->set(GithubAppClient::class, $this->createMock(GithubAppClient::class));

        /** @var EventProcessorService $eventProcessorService */
        $eventProcessorService = $this->getContainer()
            ->get(EventProcessorService::class);

        $this->assertEquals(
            [
                Event::CONFIGURATION_FILE_CHANGE->value,
                Event::INGEST_FAILURE->value,
                Event::INGEST_STARTED->value,
                Event::INGEST_SUCCESS->value,
                Event::JOB_STATE_CHANGE->value,
            ],
            array_keys($eventProcessorService->getRegisteredProcessors())
        );
    }
}
