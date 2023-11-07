<?php

namespace App\Tests\Event;

use App\Client\DynamoDbClient;
use App\Client\EventBridgeEventClient;
use App\Event\JobStateChangeEventProcessor;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\Job;
use App\Service\EventStoreService;
use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\JobStateChange;
use Packages\Event\Model\Upload;
use Packages\Models\Enum\JobState;
use Packages\Models\Enum\Provider;
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
                    $this->createMock(Upload::class)
                )
            )
        );
    }

    public function testHandlingEventWithNoExistingStateChanges(): void
    {
        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->expects($this->never())
            ->method('reduceStateChangesToEvent');
        $mockEventStoreService->expects($this->once())
            ->method('getStateChangeForEvent')
            ->with(
                null,
                $this->isInstanceOf(Job::class)
            )
            ->willReturn(['mock' => 'change']);

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getStateChangesForEvent')
            ->willReturn(new EventStateChangeCollection([]));
        $mockDynamoDbClient->expects($this->once())
            ->method('storeStateChange')
            ->with(
                $this->isInstanceOf(Job::class),
                1,
                ['mock' => 'change']
            )
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

        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->expects($this->once())
            ->method('reduceStateChangesToEvent')
            ->willReturn($mockJob);
        $mockEventStoreService->expects($this->once())
            ->method('getStateChangeForEvent')
            ->with(
                $mockJob,
                $this->isInstanceOf(Job::class)
            )
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
        $mockDynamoDbClient->expects($this->once())
            ->method('storeStateChange')
            ->with(
                $this->isInstanceOf(Job::class),
                2,
                ['mock' => 'change']
            )
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

    public function testGetEvent(): void
    {
        $this->assertEquals(
            Event::JOB_STATE_CHANGE->value,
            JobStateChangeEventProcessor::getEvent()
        );
    }
}
