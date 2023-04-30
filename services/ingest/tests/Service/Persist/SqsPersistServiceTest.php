<?php

namespace App\Tests\Service\Persist;

use App\Model\Event\IngestCompleteEvent;
use App\Model\Project;
use App\Service\Persist\BigQueryPersistService;
use App\Service\Persist\SqsPersistService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Stamp\SentStamp;

class SqsPersistServiceTest extends TestCase
{
    public function testPersist(): void
    {
        $messageBus = $this->createMock(MessageBus::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                new IngestCompleteEvent('mock-uuid')
            )
            ->willReturn(Envelope::wrap(
                new IngestCompleteEvent('mock-uuid'),
                [new SentStamp('')]
            ));

        $sqsPersistService = new SqsPersistService($messageBus);

        $successful = $sqsPersistService->persist($this->createMock(Project::class), 'mock-uuid');

        $this->assertTrue($successful);
    }

    public function testGetPriority(): void
    {
        // The SQS message should **always** be persisted after the BigQuery data
        // as been persisted.
        $this->assertTrue(SqsPersistService::getPriority() < BigQueryPersistService::getPriority());
    }
}
