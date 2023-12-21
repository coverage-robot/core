<?php

namespace App\Tests\Event;

use App\Client\EventBridgeEventClient;
use App\Enum\OrchestratedEventState;
use App\Event\JobStateChangeEventProcessor;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\Finalised;
use App\Model\Ingestion;
use App\Model\Job;
use App\Service\EventStoreService;
use App\Tests\Mock\FakeEventStoreRecorderBackoffStrategy;
use App\Tests\Mock\FakeReadyToFinaliseBackoffStrategy;
use DateInterval;
use DateTimeImmutable;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Enum\JobState;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\JobStateChange;
use Packages\Event\Model\Upload;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class JobStateChangeEventProcessorTest extends TestCase
{
    public function testHandlingInvalidEvent(): void
    {
        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            $this->createMock(EventStoreService::class),
            $this->createMock(EventBridgeEventClient::class),
            new NullLogger(),
            new FakeEventStoreRecorderBackoffStrategy()
        );

        // Ensure any backoff which occurs when waiting to finalise the coverage
        // is skipped.
        $jobStateChangeEventProcessor->withReadyToFinaliseBackoffStrategy(new FakeReadyToFinaliseBackoffStrategy());

        $this->assertFalse(
            $jobStateChangeEventProcessor->process(
                new IngestSuccess(
                    $this->createMock(Upload::class),
                    new DateTimeImmutable()
                )
            )
        );
    }

    public function testHandlingEventWithNoExistingStateChanges(): void
    {
        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->expects($this->once())
            ->method('getAllStateChangesForEvent')
            ->willReturn(new EventStateChangeCollection([]));
        $mockEventStoreService->expects($this->exactly(2))
            ->method('storeStateChange')
            ->willReturn($this->createMock(EventStateChange::class));

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            $mockEventStoreService,
            $this->createMock(EventBridgeEventClient::class),
            new NullLogger(),
            new FakeEventStoreRecorderBackoffStrategy()
        );

        // Ensure any backoff which occurs when waiting to finalise the coverage
        // is skipped.
        $jobStateChangeEventProcessor->withReadyToFinaliseBackoffStrategy(new FakeReadyToFinaliseBackoffStrategy());

        $this->assertTrue(
            $jobStateChangeEventProcessor->process(
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

    public function testStateChangeIsDroppedForOutOfOrderEvents(): void
    {
        $eventTime = new DateTimeImmutable('2021-01-01T00:00:00Z');

        $mockJob = $this->createMock(Job::class);
        $mockJob->method('getEventTime')
            ->willReturn($eventTime);

        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->expects($this->once())
            ->method('reduceStateChangesToEvent')
            ->willReturn($mockJob);
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

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            $mockEventStoreService,
            $this->createMock(EventBridgeEventClient::class),
            new NullLogger(),
            new FakeEventStoreRecorderBackoffStrategy()
        );

        // Ensure any backoff which occurs when waiting to finalise the coverage
        // is skipped.
        $jobStateChangeEventProcessor->withReadyToFinaliseBackoffStrategy(new FakeReadyToFinaliseBackoffStrategy());

        $this->assertFalse(
            $jobStateChangeEventProcessor->process(
                new JobStateChange(
                    provider: Provider::GITHUB,
                    owner: 'owner',
                    repository: 'repository',
                    ref: 'ref',
                    commit: 'commit',
                    parent: ['parent-1'],
                    externalId: 'external-id',
                    state: JobState::COMPLETED,
                    eventTime: $eventTime->sub(new DateInterval('PT10S'))
                )
            )
        );
    }

    public function testGetEvent(): void
    {
        $this->assertEquals(
            Event::JOB_STATE_CHANGE->value,
            JobStateChangeEventProcessor::getEvent()
        );
    }

    public function testHandlingEventWhenOthersOngoing(): void
    {
        $mockJob = $this->createMock(Ingestion::class);
        $mockJob->method('getState')
            ->willReturn(OrchestratedEventState::ONGOING);
        $mockJob->expects($this->once())
            ->method('getEventTime')
            ->willReturn(new DateTimeImmutable());

        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->expects($this->exactly(2))
            ->method('reduceStateChangesToEvent')
            ->willReturnOnConsecutiveCalls(
                $mockJob,
                $mockJob,
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

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            $mockEventStoreService,
            $mockEventBridgeEventClient,
            new NullLogger(),
            new FakeEventStoreRecorderBackoffStrategy()
        );

        // Ensure any backoff which occurs when waiting to finalise the coverage
        // is skipped.
        $jobStateChangeEventProcessor->withReadyToFinaliseBackoffStrategy(new FakeReadyToFinaliseBackoffStrategy());

        $this->assertTrue(
            $jobStateChangeEventProcessor->process(
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

    public function testNotFinalisingWhenAlreadyBeenFinalised(): void
    {
        $mockJob = $this->createMock(Job::class);
        $mockJob->method('getState')
            ->willReturn(OrchestratedEventState::SUCCESS);
        $mockJob->method('getEventTime')
            ->willReturn(new DateTimeImmutable());
        $mockFinalised = $this->createMock(Finalised::class);
        $mockFinalised->method('getEventTime')
            ->willReturn(new DateTimeImmutable());
        $mockFinalised->method('getState')
            ->willReturn(OrchestratedEventState::SUCCESS);

        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->method('reduceStateChangesToEvent')
            ->willReturnOnConsecutiveCalls(
                $mockJob,
                $mockFinalised,
                $mockJob,
                $mockFinalised
            );
        $mockEventStoreService->expects($this->atLeastOnce())
            ->method('getAllStateChangesForCommit')
            ->willReturn(
                [
                    new EventStateChangeCollection([]),
                    new EventStateChangeCollection([]),
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

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            $mockEventStoreService,
            $mockEventBridgeEventClient,
            new NullLogger(),
            new FakeEventStoreRecorderBackoffStrategy()
        );

        // Ensure any backoff which occurs when waiting to finalise the coverage
        // is skipped.
        $jobStateChangeEventProcessor->withReadyToFinaliseBackoffStrategy(new FakeReadyToFinaliseBackoffStrategy());

        $this->assertTrue(
            $jobStateChangeEventProcessor->process(
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
}
