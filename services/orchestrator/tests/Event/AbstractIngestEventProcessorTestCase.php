<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Client\EventBridgeEventClient;
use App\Enum\OrchestratedEventState;
use App\Event\AbstractIngestEventProcessor;
use App\Event\Backoff\ReadyToFinaliseBackoffStrategy;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\Ingestion;
use App\Service\EventStoreServiceInterface;
use App\Tests\Mock\FakeBackoffStrategy;
use App\Tests\Mock\FakeReadyToFinaliseBackoffStrategy;
use DateInterval;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Client\EventBusClientInterface;
use Packages\Event\Enum\JobState;
use Packages\Event\Model\IngestFailure;
use Packages\Event\Model\IngestStarted;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\JobStateChange;
use Packages\Event\Model\Upload;
use Packages\Message\Client\SqsClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

abstract class AbstractIngestEventProcessorTestCase extends TestCase
{
    /**
     * @return class-string<AbstractIngestEventProcessor>
     */
    abstract public static function getEventProcessor(): string;

    /**
     * @return class-string<IngestSuccess|IngestFailure|IngestStarted>
     */
    abstract public static function getEvent(): string;

    public function testHandlingInvalidEvent(): void
    {
        $ingestEventProcessor = $this->getIngestEventProcessor(
            $this->createMock(EventStoreServiceInterface::class),
            $this->createMock(EventBusClientInterface::class),
            $this->createMock(SqsClientInterface::class)
        );

        $this->assertFalse(
            $ingestEventProcessor->process(
                new JobStateChange(
                    provider: Provider::GITHUB,
                    projectId: '0192c0b2-a63e-7c29-8636-beb65b9097ee',
                    owner: 'owner',
                    repository: 'repository',
                    ref: 'ref',
                    commit: 'commit',
                    parent: ['parent-1'],
                    externalId: 'external-id',
                    triggeredByExternalId: 'mock-github-app',
                    state: JobState::COMPLETED
                )
            )
        );
    }

    public function testHandlingEventWithNoExistingStateChanges(): void
    {
        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
        $mockEventStoreService->expects($this->once())
            ->method('reduceStateChangesToEvent')
            ->willReturn(null);
        $mockEventStoreService->method('getStateChangesBetweenEvent')
            ->willReturn(['mock' => 'change']);

        $mockEventStoreService->expects($this->once())
            ->method('getAllStateChangesForEvent')
            ->willReturn(new EventStateChangeCollection([]));
        $mockEventStoreService->expects($this->atMost(2))
            ->method('storeStateChange')
            ->willReturn(new EventStateChange(
                provider: Provider::GITHUB,
                identifier: 'mock-identifier',
                owner: 'mock-owner',
                repository: 'mock-repository',
                version: 1,
                event: []
            ));

        $ingestEventProcessor = $this->getIngestEventProcessor(
            $mockEventStoreService,
            $this->createMock(EventBusClientInterface::class),
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
                        tag: new Tag('mock-tag', 'mock-commit', [11]),
                        baseCommit: 'commit-on-main',
                        baseRef: 'main'
                    ),
                    new DateTimeImmutable()
                )
            )
        );
    }

    public function testStateChangeIsDroppedForOutOfOrderEvents(): void
    {
        $eventTime = new DateTimeImmutable('2021-01-01T00:00:00Z');

        $mockIngestion = new Ingestion(
            provider: Provider::GITHUB,
            projectId: 'mock-project-id',
            owner: 'mock-owner',
            repository: 'mock-repository',
            commit: 'mock-commit',
            uploadId: 'mock-upload-id',
            state: OrchestratedEventState::SUCCESS,
            eventTime: $eventTime
        );

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
        $mockEventStoreService->expects($this->once())
            ->method('reduceStateChangesToEvent')
            ->willReturn($mockIngestion);
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
        $mockEventStoreService->expects($this->never())
            ->method('storeStateChange');

        $ingestEventProcessor = $this->getIngestEventProcessor(
            $mockEventStoreService,
            $this->createMock(EventBusClientInterface::class),
            $this->createMock(SqsClientInterface::class)
        );

        $this->assertFalse(
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
                        tag: new Tag('mock-tag', 'mock-commit', [12]),
                        baseCommit: 'commit-on-main',
                        baseRef: 'main',
                        eventTime: $eventTime->sub(new DateInterval('PT30S'))
                    ),
                    $eventTime->sub(new DateInterval('PT10S'))
                )
            )
        );
    }

    protected function getIngestEventProcessor(
        EventStoreServiceInterface $mockEventStoreService,
        EventBusClientInterface $eventBusClient,
        SqsClientInterface $sqsClient
    ): AbstractIngestEventProcessor {
        $ingestEventProcessor = new ($this::getEventProcessor())(
            $mockEventStoreService,
            $eventBusClient,
            new NullLogger(),
            new FakeBackoffStrategy(),
            $sqsClient
        );

        // Ensure any backoff which occurs when waiting to finalise the coverage
        // is skipped.
        $ingestEventProcessor->withReadyToFinaliseBackoffStrategy(
            new FakeBackoffStrategy(ReadyToFinaliseBackoffStrategy::class)
        );

        return $ingestEventProcessor;
    }
}
