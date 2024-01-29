<?php

namespace App\Tests\Event;

use App\Client\EventBridgeEventClient;
use App\Enum\OrchestratedEventState;
use App\Event\IngestSuccessEventProcessor;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\Finalised;
use App\Model\Ingestion;
use App\Service\EventStoreServiceInterface;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Client\EventBusClient;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\Upload;

final class IngestSuccessEventProcessorTest extends AbstractIngestEventProcessorTestCase
{
    #[Override]
    public static function getEventProcessor(): string
    {
        return IngestSuccessEventProcessor::class;
    }

    #[Override]
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

    public function testHandlingEventWhenOthersOngoing(): void
    {
        $mockIngestion = new Ingestion(
            uploadId: 'mock-upload-id',
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            commit: 'mock-commit',
            state: OrchestratedEventState::ONGOING,
            eventTime: new DateTimeImmutable(),
        );

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
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
            ->willReturn(new EventStateChange(
                provider: Provider::GITHUB,
                identifier: 'mock-identifier',
                owner: 'mock-owner',
                repository: 'mock-repository',
                version: 1,
                event: ['mock' => 'change'],
            ));

        $mockEventBusClient = $this->createMock(EventBusClient::class);
        $mockEventBusClient->expects($this->never())
            ->method('fireEvent');

        $ingestEventProcessor = $this->getIngestEventProcessor(
            $mockEventStoreService,
            $mockEventBusClient
        );

        $this->assertTrue(
            $ingestEventProcessor->process(
                new ($this::getEvent())(
                    new Upload(
                        uploadId: 'mock-upload',
                        provider: Provider::GITHUB,
                        owner: 'mock-owner',
                        repository: 'mock-repository',
                        commit: 'mock-commit',
                        parent: [],
                        ref: 'mock-ref',
                        projectRoot: '',
                        tag: new Tag('mock-tag', 'mock-commit'),
                    ),
                    new DateTimeImmutable()
                )
            )
        );
    }

    public function testNotFinalisingWhenAlreadyBeenFinalised(): void
    {
        $mockIngestion = new Ingestion(
            uploadId: 'mock-upload-id',
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            commit: 'mock-commit',
            state: OrchestratedEventState::SUCCESS,
            eventTime: new DateTimeImmutable(),
        );
        $mockFinalised = new Finalised(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            commit: 'mock-commit',
            ref: 'mock-ref',
            pullRequest: null,
            state: OrchestratedEventState::SUCCESS,
            eventTime: new DateTimeImmutable(),
        );

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
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
            ->willReturn(new EventStateChange(
                provider: Provider::GITHUB,
                identifier: 'mock-identifier',
                owner: 'mock-owner',
                repository: 'mock-repository',
                version: 1,
                event: ['mock' => 'change'],
            ));

        $mockEventBusClient = $this->createMock(EventBusClient::class);
        $mockEventBusClient->expects($this->never())
            ->method('fireEvent');

        $ingestEventProcessor = $this->getIngestEventProcessor(
            $mockEventStoreService,
            $mockEventBusClient
        );

        $this->assertTrue(
            $ingestEventProcessor->process(
                new ($this::getEvent())(
                    new Upload(
                        uploadId: 'mock-upload',
                        provider: Provider::GITHUB,
                        owner: 'mock-owner',
                        repository: 'mock-repository',
                        commit: 'mock-commit',
                        parent: [],
                        ref: 'mock-ref',
                        projectRoot: '',
                        tag: new Tag('mock-tag', 'mock-commit')
                    ),
                    new DateTimeImmutable()
                )
            )
        );
    }
}
