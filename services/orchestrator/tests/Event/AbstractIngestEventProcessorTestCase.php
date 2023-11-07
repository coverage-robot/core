<?php

namespace App\Tests\Event;

use App\Client\DynamoDbClient;
use App\Event\AbstractIngestEventProcessor;
use App\Model\Ingestion;
use App\Model\Job;
use App\Service\EventStoreService;
use DateTimeImmutable;
use Packages\Event\Model\IngestFailure;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\JobStateChange;
use Packages\Event\Model\Upload;
use Packages\Models\Enum\JobState;
use Packages\Models\Enum\Provider;
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
     * @return class-string<IngestSuccess|IngestFailure>
     */
    abstract public static function getEvent(): string;

    public function testHandlingInvalidEvent(): void
    {
        $ingestEventProcessor = new ($this::getEventProcessor())(
            $this->createMock(EventStoreService::class),
            $this->createMock(DynamoDbClient::class),
            new NullLogger()
        );

        $this->assertFalse(
            $ingestEventProcessor->process(
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

    public function testHandlingEventWithNoExistingStateChanges(): void
    {
        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->expects($this->never())
            ->method('reduceStateChangesToEvent');
        $mockEventStoreService->expects($this->once())
            ->method('getStateChangeForEvent')
            ->with(
                null,
                $this->isInstanceOf(Ingestion::class)
            )
            ->willReturn(['mock' => 'change']);

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getStateChangesForEvent')
            ->willReturn([]);
        $mockDynamoDbClient->expects($this->once())
            ->method('storeStateChange')
            ->with(
                $this->isInstanceOf(Ingestion::class),
                1,
                ['mock' => 'change']
            )
            ->willReturn(true);

        $ingestEventProcessor = new ($this::getEventProcessor())(
            $mockEventStoreService,
            $mockDynamoDbClient,
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
                    )
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
                $this->isInstanceOf(Ingestion::class)
            )
            ->willReturn(['mock' => 'change']);

        $mockDynamoDbClient = $this->createMock(DynamoDbClient::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getStateChangesForEvent')
            ->willReturn([
                ['existing' => 'change']
            ]);
        $mockDynamoDbClient->expects($this->once())
            ->method('storeStateChange')
            ->with(
                $this->isInstanceOf(Ingestion::class),
                2,
                ['mock' => 'change']
            )
            ->willReturn(true);

        $ingestEventProcessor = new ($this::getEventProcessor())(
            $mockEventStoreService,
            $mockDynamoDbClient,
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
                    )
                )
            )
        );
    }
}
