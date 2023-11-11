<?php

namespace App\Tests\Event;

use App\Client\DynamoDbClient;
use App\Client\EventBridgeEventClient;
use App\Event\AbstractIngestEventProcessor;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\Ingestion;
use App\Service\EventStoreService;
use DateInterval;
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
            $this->createMock(EventBridgeEventClient::class),
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
            ->willReturn(new EventStateChangeCollection([]));
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
            $this->createMock(EventBridgeEventClient::class),
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
        $mockIngestion = $this->createMock(Ingestion::class);
        $mockIngestion->expects($this->once())
            ->method('getEventTime')
            ->willReturn(new DateTimeImmutable());

        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->expects($this->once())
            ->method('reduceStateChangesToEvent')
            ->willReturn($mockIngestion);
        $mockEventStoreService->expects($this->once())
            ->method('getStateChangeForEvent')
            ->with(
                $mockIngestion,
                $this->isInstanceOf(Ingestion::class)
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
                $this->isInstanceOf(Ingestion::class),
                2,
                ['mock' => 'change']
            )
            ->willReturn(true);

        $ingestEventProcessor = new ($this::getEventProcessor())(
            $mockEventStoreService,
            $mockDynamoDbClient,
            $this->createMock(EventBridgeEventClient::class),
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

        $ingestEventProcessor = new ($this::getEventProcessor())(
            $mockEventStoreService,
            $mockDynamoDbClient,
            $this->createMock(EventBridgeEventClient::class),
            new NullLogger()
        );

        $this->assertFalse(
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
                        $eventTime->sub(new DateInterval('PT10S'))
                    )
                )
            )
        );
    }
}
