<?php

namespace App\Tests\Event;

use App\Client\DynamoDbClient;
use App\Client\EventBridgeEventClient;
use App\Enum\OrchestratedEventState;
use App\Event\JobStateChangeEventProcessor;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\Finalised;
use App\Model\Job;
use App\Service\EventStoreService;
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
            $this->createMock(DynamoDbClient::class),
            $this->createMock(EventBridgeEventClient::class),
            new NullLogger()
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
        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->expects($this->never())
            ->method('reduceStateChangesToEvent');
        $mockEventStoreService->method('getStateChangeForEvent')
            ->willReturn(['mock' => 'change']);

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getStateChangesForEvent')
            ->willReturn(new EventStateChangeCollection([]));
        $mockDynamoDbClient->expects($this->exactly(2))
            ->method('storeStateChange')
            ->willReturn(true);

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            $mockEventStoreService,
            $mockDynamoDbClient,
            $this->createMock(EventBridgeEventClient::class),
            new NullLogger()
        );

        $this->assertTrue(
            $jobStateChangeEventProcessor->process(
                new JobStateChange(
                    Provider::GITHUB,
                    'owner',
                    'repository',
                    'ref',
                    'commit',
                    null,
                    'external-id',
                    0,
                    JobState::COMPLETED,
                    JobState::IN_PROGRESS,
                    false,
                    new DateTimeImmutable()
                )
            )
        );
    }

    public function testHandlingEventWithExistingStateChanges(): void
    {
        $mockJob = $this->createMock(Job::class);
        $mockJob->expects($this->once())
            ->method('getEventTime')
            ->willReturn(new DateTimeImmutable());

        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->expects($this->once())
            ->method('reduceStateChangesToEvent')
            ->willReturn($mockJob);
        $mockEventStoreService->method('getStateChangeForEvent')
            ->willReturn(['mock' => 'change']);

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
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
        $mockDynamoDbClient->expects($this->exactly(2))
            ->method('storeStateChange')
            ->willReturn(true);

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            $mockEventStoreService,
            $mockDynamoDbClient,
            $this->createMock(EventBridgeEventClient::class),
            new NullLogger()
        );

        $this->assertTrue(
            $jobStateChangeEventProcessor->process(
                new JobStateChange(
                    Provider::GITHUB,
                    'owner',
                    'repository',
                    'ref',
                    'commit',
                    null,
                    'external-id',
                    0,
                    JobState::COMPLETED,
                    JobState::IN_PROGRESS,
                    false,
                    new DateTimeImmutable()
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
        $mockEventStoreService->expects($this->never())
            ->method('getStateChangeForEvent');

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
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
        $mockDynamoDbClient->expects($this->never())
            ->method('storeStateChange');

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            $mockEventStoreService,
            $mockDynamoDbClient,
            $this->createMock(EventBridgeEventClient::class),
            new NullLogger()
        );

        $this->assertFalse(
            $jobStateChangeEventProcessor->process(
                new JobStateChange(
                    Provider::GITHUB,
                    'owner',
                    'repository',
                    'ref',
                    'commit',
                    null,
                    'external-id',
                    0,
                    JobState::COMPLETED,
                    JobState::IN_PROGRESS,
                    false,
                    $eventTime->sub(new DateInterval('PT10S'))
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

    public function testNotFinalisingWhenAlreadyBeenFinalised(): void
    {
        $mockJob = $this->createMock(Job::class);
        $mockJob->method('getState')
            ->willReturn(OrchestratedEventState::SUCCESS);
        $mockJob->expects($this->once())
            ->method('getEventTime')
            ->willReturn(new DateTimeImmutable());
        $mockFinalised = $this->createMock(Finalised::class);
        $mockFinalised->method('getState')
            ->willReturn(OrchestratedEventState::SUCCESS);

        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->expects($this->exactly(3))
            ->method('reduceStateChangesToEvent')
            ->willReturnOnConsecutiveCalls(
                $mockJob,
                $mockFinalised,
                $mockFinalised
            );
        $mockEventStoreService->expects($this->once())
            ->method('getStateChangeForEvent')
            ->with(
                $mockJob,
                $this->isInstanceOf(Job::class)
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
                $this->isInstanceOf(Job::class),
                2,
                ['mock' => 'change']
            )
            ->willReturn(true);

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventClient->expects($this->never())
            ->method('publishEvent');

        $jobStateChangeEventProcessor = new JobStateChangeEventProcessor(
            $mockEventStoreService,
            $mockDynamoDbClient,
            $mockEventBridgeEventClient,
            new NullLogger()
        );

        $this->assertTrue(
            $jobStateChangeEventProcessor->process(
                new JobStateChange(
                    Provider::GITHUB,
                    'owner',
                    'repository',
                    'ref',
                    'commit',
                    null,
                    'external-id',
                    0,
                    JobState::COMPLETED,
                    JobState::COMPLETED,
                    false,
                    new DateTimeImmutable()
                )
            )
        );
    }
}
