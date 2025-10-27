<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Enum\OrchestratedEventState;
use App\Event\IngestFailureEventProcessor;
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
use Packages\Event\Client\EventBusClientInterface;
use Packages\Event\Model\IngestFailure;
use Packages\Event\Model\Upload;
use Packages\Message\Client\SqsClientInterface;

final class IngestFailureEventProcessorTest extends AbstractIngestEventProcessorTestCase
{
    #[Override]
    public static function getEventProcessor(): string
    {
        return IngestFailureEventProcessor::class;
    }

    #[Override]
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
        $mockIngestion = new Ingestion(
            provider: Provider::GITHUB,
            projectId: 'mock-project-id',
            owner: 'mock-owner',
            repository: 'mock-repository',
            commit: 'mock-commit',
            uploadId: 'mock-upload',
            state: OrchestratedEventState::ONGOING,
            eventTime: new DateTimeImmutable()
        );

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
        $mockEventStoreService->expects($this->exactly(2))
            ->method('reduceStateChangesToEvent')->willReturn($mockIngestion);
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
                        ['mock' => 'change']
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

        $mockEventBusClient = $this->createMock(EventBusClientInterface::class);
        $mockEventBusClient->expects($this->never())
            ->method('fireEvent');

        $ingestEventProcessor = $this->getIngestEventProcessor(
            $mockEventStoreService,
            $mockEventBusClient,
            $this->createMock(SqsClientInterface::class)
        );

        $this->assertTrue(
            $ingestEventProcessor->process(
                new ($this::getEvent())(
                    new Upload(
                        uploadId: 'mock-upload',
                        provider: Provider::GITHUB,
                        projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
                        owner: 'mock-owner',
                        repository: 'mock-repository',
                        commit: 'mock-commit',
                        parent: [],
                        ref: 'mock-ref',
                        projectRoot: '',
                        tag: new Tag('mock-tag', 'mock-commit', [9])
                    ),
                    new DateTimeImmutable()
                )
            )
        );
    }

    public function testNotFinalisingWhenAlreadyBeenFinalised(): void
    {
        $mockIngestion = new Ingestion(
            provider: Provider::GITHUB,
            projectId: 'mock-project-id',
            owner: 'mock-owner',
            repository: 'mock-repository',
            commit: 'mock-commit',
            uploadId: 'mock-upload',
            state: OrchestratedEventState::SUCCESS,
            eventTime: new DateTimeImmutable()
        );
        $mockFinalised = new Finalised(
            provider: Provider::GITHUB,
            projectId: 'mock-project-id',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            state: OrchestratedEventState::SUCCESS,
            pullRequest: null,
            eventTime: new DateTimeImmutable()
        );

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
        $mockEventStoreService->expects($this->exactly(5))
            ->method('reduceStateChangesToEvent')
            ->willReturnOnConsecutiveCalls(
                $mockIngestion,
                $mockFinalised,
                $mockIngestion,
                $mockFinalised,
                $mockIngestion
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
                        ['mock' => 'change']
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

        $mockEventBusClient = $this->createMock(EventBusClientInterface::class);
        $mockEventBusClient->expects($this->never())
            ->method('fireEvent');

        $ingestEventProcessor = $this->getIngestEventProcessor(
            $mockEventStoreService,
            $mockEventBusClient,
            $this->createMock(SqsClientInterface::class)
        );

        $this->assertTrue(
            $ingestEventProcessor->process(
                new ($this::getEvent())(
                    new Upload(
                        uploadId: 'mock-upload',
                        provider: Provider::GITHUB,
                        projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
                        owner: 'mock-owner',
                        repository: 'mock-repository',
                        commit: 'mock-commit',
                        parent: [],
                        ref: 'mock-ref',
                        projectRoot: '',
                        tag: new Tag('mock-tag', 'mock-commit', [8]),
                    ),
                    new DateTimeImmutable()
                )
            )
        );
    }
}
