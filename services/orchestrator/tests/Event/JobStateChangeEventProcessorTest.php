<?php

namespace App\Tests\Event;

use App\Client\EventBridgeEventClient;
use App\Enum\OrchestratedEventState;
use App\Event\Backoff\ReadyToFinaliseBackoffStrategy;
use App\Event\JobStateChangeEventProcessor;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\Finalised;
use App\Model\Job;
use App\Service\EventStoreServiceInterface;
use App\Tests\Mock\FakeBackoffStrategy;
use App\Tests\Mock\FakeReadyToFinaliseBackoffStrategy;
use DateInterval;
use DateTimeImmutable;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Client\EventBusClient;
use Packages\Event\Enum\JobState;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\JobStateChange;
use Packages\Event\Model\Upload;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class JobStateChangeEventProcessorTest extends TestCase
{
    public function testHandlingInvalidEvent(): void
    {
        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            $this->createMock(EventStoreServiceInterface::class),
            $this->createMock(EventBusClient::class),
            new NullLogger(),
            new FakeBackoffStrategy()
        );

        // Ensure any backoff which occurs when waiting to finalise the coverage
        // is skipped.
        $jobStateChangeEventProcessor->withReadyToFinaliseBackoffStrategy(
            new FakeBackoffStrategy(ReadyToFinaliseBackoffStrategy::class)
        );

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
        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
        $mockEventStoreService->expects($this->once())
            ->method('getAllStateChangesForEvent')
            ->willReturn(new EventStateChangeCollection([]));
        $mockEventStoreService->expects($this->exactly(2))
            ->method('storeStateChange')
            ->willReturn(new EventStateChange(
                Provider::GITHUB,
                'mock-identifier',
                'mock-owner',
                'mock-repository',
                1,
                ['mock' => 'change'],
                null
            ));

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            $mockEventStoreService,
            $this->createMock(EventBusClient::class),
            new NullLogger(),
            new FakeBackoffStrategy()
        );

        // Ensure any backoff which occurs when waiting to finalise the coverage
        // is skipped.
        $jobStateChangeEventProcessor->withReadyToFinaliseBackoffStrategy(
            new FakeBackoffStrategy(ReadyToFinaliseBackoffStrategy::class)
        );

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

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
        $mockEventStoreService->expects($this->once())
            ->method('reduceStateChangesToEvent')
            ->willReturn(new Job(
                provider: Provider::GITHUB,
                owner: 'owner',
                repository: 'repository',
                commit: 'commit',
                state: OrchestratedEventState::ONGOING,
                eventTime: $eventTime,
                externalId: 'external-id'
            ));
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
            $this->createMock(EventBusClient::class),
            new NullLogger(),
            new FakeBackoffStrategy()
        );

        // Ensure any backoff which occurs when waiting to finalise the coverage
        // is skipped.
        $jobStateChangeEventProcessor->withReadyToFinaliseBackoffStrategy(
            new FakeBackoffStrategy(ReadyToFinaliseBackoffStrategy::class)
        );

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
        $mockJob = new Job(
            provider: Provider::GITHUB,
            owner: 'owner',
            repository: 'repository',
            commit: 'commit',
            state: OrchestratedEventState::ONGOING,
            eventTime: new DateTimeImmutable(),
            externalId: 'external-id'
        );

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
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

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            $mockEventStoreService,
            $mockEventBusClient,
            new NullLogger(),
            new FakeBackoffStrategy()
        );

        // Ensure any backoff which occurs when waiting to finalise the coverage
        // is skipped.
        $jobStateChangeEventProcessor->withReadyToFinaliseBackoffStrategy(
            new FakeBackoffStrategy(ReadyToFinaliseBackoffStrategy::class)
        );

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
        $mockJob = new Job(
            provider: Provider::GITHUB,
            owner: 'owner',
            repository: 'repository',
            commit: 'commit',
            state: OrchestratedEventState::SUCCESS,
            eventTime: new DateTimeImmutable(),
            externalId: 'external-id'
        );
        $mockFinalised = new Finalised(
            provider: Provider::GITHUB,
            owner: 'owner',
            repository: 'repository',
            ref: 'ref',
            commit: 'commit',
            state: OrchestratedEventState::SUCCESS,
            pullRequest: null,
            eventTime: new DateTimeImmutable()
        );

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
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

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            $mockEventStoreService,
            $mockEventBusClient,
            new NullLogger(),
            new FakeBackoffStrategy()
        );

        // Ensure any backoff which occurs when waiting to finalise the coverage
        // is skipped.
        $jobStateChangeEventProcessor->withReadyToFinaliseBackoffStrategy(
            new FakeBackoffStrategy(ReadyToFinaliseBackoffStrategy::class)
        );

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
