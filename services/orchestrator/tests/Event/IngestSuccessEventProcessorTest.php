<?php

namespace App\Tests\Event;

use App\Client\DynamoDbClient;
use App\Client\EventBridgeEventClient;
use App\Enum\OrchestratedEventState;
use App\Event\IngestSuccessEventProcessor;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\Finalised;
use App\Model\Ingestion;
use App\Service\EventStoreService;
use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\Upload;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Tag;
use Psr\Log\NullLogger;

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

    public function testNotFinalisingWhenAlreadyBeenFinalised(): void
    {
        $mockIngestion = $this->createMock(Ingestion::class);
        $mockIngestion->method('getState')
            ->willReturn(OrchestratedEventState::SUCCESS);
        $mockIngestion->expects($this->once())
            ->method('getEventTime')
            ->willReturn(new DateTimeImmutable());
        $mockFinalised = $this->createMock(Finalised::class);
        $mockFinalised->method('getState')
            ->willReturn(OrchestratedEventState::SUCCESS);

        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->expects($this->exactly(3))
            ->method('reduceStateChangesToEvent')
            ->willReturnOnConsecutiveCalls(
                $mockIngestion,
                $mockFinalised,
                $mockFinalised
            );
        $mockEventStoreService->expects($this->once())
            ->method('getStateChangeForEvent')
            ->with(
                $mockIngestion,
                $this->isInstanceOf(Ingestion::class)
            )
            ->willReturn(['mock' => 'change']);

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->atLeastOnce())
            ->method('getEventStateChangesForCommit')
            ->willReturn(
                [
                    new EventStateChangeCollection([])
                ]
            );
        $mockDynamoDbClient->expects($this->once())
            ->method('getStateChangesForEvent')
            ->willReturn(
                new EventStateChangeCollection([
                    new EventStateChange(
                        Provider::GITHUB,
                        'mock-identifier',
                        'mock-owner',
                        'mock-repository',
                        1,
                        ['mock' => 'change'],
                        1
                    )
                ])
            );
        $mockDynamoDbClient->expects($this->once())
            ->method('storeStateChange')
            ->with(
                $this->isInstanceOf(Ingestion::class),
                2,
                ['mock' => 'change']
            )
            ->willReturn(true);

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventClient->expects($this->never())
            ->method('publishEvent');

        $ingestEventProcessor = new ($this::getEventProcessor())(
            $mockEventStoreService,
            $mockDynamoDbClient,
            $mockEventBridgeEventClient,
            new NullLogger()
        );

        $this->assertTrue(
            $ingestEventProcessor->process(
                new ($this::getEvent())(
                    new Upload(
                        'mock-upload',
                        Provider::GITHUB,
                        'mock-owner',
                        'mock-repository',
                        'mock-commit',
                        [],
                        'mock-ref',
                        '',
                        null,
                        new Tag('mock-tag', 'mock-commit'),
                        null
                    ),
                    new DateTimeImmutable()
                )
            )
        );
    }
}
