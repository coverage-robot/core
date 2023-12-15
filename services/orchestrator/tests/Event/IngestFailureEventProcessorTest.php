<?php

namespace App\Tests\Event;

use App\Client\EventBridgeEventClient;
use App\Enum\OrchestratedEventState;
use App\Event\IngestFailureEventProcessor;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\Finalised;
use App\Model\Ingestion;
use App\Service\EventStoreService;
use DateTimeImmutable;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\IngestFailure;
use Packages\Event\Model\Upload;
use Packages\Models\Model\Tag;

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

    public function testHandlingEventWhenOthersOngoing(): void
    {
        $mockIngestion = $this->createMock(Ingestion::class);
        $mockIngestion->method('getState')
            ->willReturn(OrchestratedEventState::ONGOING);
        $mockIngestion->expects($this->once())
            ->method('getEventTime')
            ->willReturn(new DateTimeImmutable());

        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->expects($this->exactly(2))
            ->method('reduceStateChangesToEvent')
            ->willReturnOnConsecutiveCalls(
                $mockIngestion,
                $mockIngestion,
            );
        $mockEventStoreService->expects($this->atLeastOnce())
            ->method('getAllStateChangesForCommit')
            ->willReturn(
                [
                    new EventStateChangeCollection([])
                ]
            );
        $mockEventStoreService->expects($this->once())
            ->method('getAllStateChangesForEvent')
            ->willReturn(
                new EventStateChangeCollection([
                    new EventStateChange(
                        Provider::GITHUB,
                        'mock-identifier',
                        'mock-owner',
                        'mock-repository',
                        1,
                        ['mock' => 'change'],
                        null
                    )
                ])
            );
        $mockEventStoreService->expects($this->once())
            ->method('storeStateChange')
            ->willReturn($this->createMock(EventStateChange::class));

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventClient->expects($this->never())
            ->method('publishEvent');

        $ingestEventProcessor = $this->getIngestEventProcessor(
            $mockEventStoreService,
            $mockEventBridgeEventClient
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
                        null,
                        null,
                        new Tag('mock-tag', 'mock-commit'),
                        null
                    ),
                    new DateTimeImmutable()
                )
            )
        );
    }

    public function testNotFinalisingWhenAlreadyBeenFinalised(): void
    {
        $mockIngestion = $this->createMock(Ingestion::class);
        $mockIngestion->method('getState')
            ->willReturn(OrchestratedEventState::SUCCESS);
        $mockIngestion->method('getEventTime')
            ->willReturn(new DateTimeImmutable());
        $mockFinalised = $this->createMock(Finalised::class);
        $mockFinalised->method('getEventTime')
            ->willReturn(new DateTimeImmutable());
        $mockFinalised->method('getState')
            ->willReturn(OrchestratedEventState::SUCCESS);

        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->method('reduceStateChangesToEvent')
            ->willReturnOnConsecutiveCalls(
                $mockIngestion,
                $mockFinalised,
                $mockIngestion,
                $mockFinalised
            );
        $mockEventStoreService->expects($this->atLeastOnce())
            ->method('getAllStateChangesForCommit')
            ->willReturn(
                [
                    new EventStateChangeCollection([]),
                    new EventStateChangeCollection([])
                ]
            );
        $mockEventStoreService->expects($this->once())
            ->method('getAllStateChangesForEvent')
            ->willReturn(
                new EventStateChangeCollection([
                    new EventStateChange(
                        Provider::GITHUB,
                        'mock-identifier',
                        'mock-owner',
                        'mock-repository',
                        1,
                        ['mock' => 'change'],
                        null
                    )
                ])
            );
        $mockEventStoreService->expects($this->once())
            ->method('storeStateChange')
            ->willReturn($this->createMock(EventStateChange::class));

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventClient->expects($this->never())
            ->method('publishEvent');

        $ingestEventProcessor = $this->getIngestEventProcessor(
            $mockEventStoreService,
            $mockEventBridgeEventClient
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
                        null,
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
