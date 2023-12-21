<?php

namespace App\Tests\Event;

use App\Client\EventBridgeEventClient;
use App\Event\AbstractIngestEventProcessor;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\Ingestion;
use App\Service\EventStoreService;
use App\Tests\Mock\FakeEventStoreRecorderBackoffStrategy;
use App\Tests\Mock\FakeReadyToFinaliseBackoffStrategy;
use DateInterval;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Enum\JobState;
use Packages\Event\Model\IngestFailure;
use Packages\Event\Model\IngestStarted;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\JobStateChange;
use Packages\Event\Model\Upload;
use Packages\Models\Model\Tag;
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
            $this->createMock(EventStoreService::class),
            $this->createMock(EventBridgeEventClient::class)
        );

        $this->assertFalse(
            $ingestEventProcessor->process(
                new JobStateChange(
                    provider: Provider::GITHUB,
                    owner: 'owner',
                    repository: 'repository',
                    ref: 'ref',
                    commit: 'commit',
                    parent: ['parent-1'],
                    externalId: 'external-id',
                    state: JobState::COMPLETED
                )
            )
        );
    }

    public function testHandlingEventWithNoExistingStateChanges(): void
    {
        $mockEventStoreService = $this->createMock(EventStoreService::class);
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
            ->willReturn($this->createMock(EventStateChange::class));

        $ingestEventProcessor = $this->getIngestEventProcessor(
            $mockEventStoreService,
            $this->createMock(EventBridgeEventClient::class)
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

        $mockIngestion = $this->createMock(Ingestion::class);
        $mockIngestion->method('getEventTime')
            ->willReturn($eventTime);

        $mockEventStoreService = $this->createMock(EventStoreService::class);
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
            $this->createMock(EventBridgeEventClient::class)
        );

        $this->assertFalse(
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
        EventStoreService $mockEventStoreService,
        EventBridgeEventClient $eventBridgeEventClient
    ): AbstractIngestEventProcessor {
        $ingestEventProcessor = new ($this::getEventProcessor())(
            $mockEventStoreService,
            $eventBridgeEventClient,
            new NullLogger(),
            new FakeEventStoreRecorderBackoffStrategy()
        );

        // Ensure any backoff which occurs when waiting to finalise the coverage
        // is skipped.
        $ingestEventProcessor->withReadyToFinaliseBackoffStrategy(new FakeReadyToFinaliseBackoffStrategy());

        return $ingestEventProcessor;
    }
}
