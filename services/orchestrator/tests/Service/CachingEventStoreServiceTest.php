<?php

namespace App\Tests\Service;

use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\OrchestratedEventInterface;
use App\Service\CachingEventStoreService;
use App\Service\EventStoreServiceInterface;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;

final class CachingEventStoreServiceTest extends TestCase
{
    public function testReducingStateChangesToEventUsesCache(): void
    {
        $mockCollection = new EventStateChangeCollection([]);
        $mockOrchestratedEvent = $this->createMock(OrchestratedEventInterface::class);

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
        $mockEventStoreService->expects($this->once())
            ->method('reduceStateChangesToEvent')
            ->willReturn($mockOrchestratedEvent);

        $cachingEventStoreService = new CachingEventStoreService($mockEventStoreService);

        $this->assertEquals(
            $mockOrchestratedEvent,
            $cachingEventStoreService->reduceStateChangesToEvent($mockCollection)
        );

        $this->assertEquals(
            $mockOrchestratedEvent,
            $cachingEventStoreService->reduceStateChangesToEvent($mockCollection)
        );
    }

    public function testGetStateChangesBetweenEvent(): void
    {
        $mockOrchestratedEvent = $this->createMock(OrchestratedEventInterface::class);

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
        $mockEventStoreService->expects($this->exactly(2))
            ->method('getStateChangesBetweenEvent')
            ->willReturn([]);

        $cachingEventStoreService = new CachingEventStoreService($mockEventStoreService);

        $this->assertEquals(
            [],
            $cachingEventStoreService->getStateChangesBetweenEvent(null, $mockOrchestratedEvent)
        );
        $this->assertEquals(
            [],
            $cachingEventStoreService->getStateChangesBetweenEvent(null, $mockOrchestratedEvent)
        );
    }

    public function testGetAllStateChangesForEvent(): void
    {
        $mockOrchestratedEvent = $this->createMock(OrchestratedEventInterface::class);

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
        $mockEventStoreService->expects($this->exactly(2))
            ->method('getAllStateChangesForEvent')
            ->willReturn(new EventStateChangeCollection([]));

        $cachingEventStoreService = new CachingEventStoreService($mockEventStoreService);

        $this->assertEquals(
            new EventStateChangeCollection([]),
            $cachingEventStoreService->getAllStateChangesForEvent($mockOrchestratedEvent)
        );
        $this->assertEquals(
            new EventStateChangeCollection([]),
            $cachingEventStoreService->getAllStateChangesForEvent($mockOrchestratedEvent)
        );
    }

    public function testGetAllStateChangesForCommit(): void
    {
        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
        $mockEventStoreService->expects($this->exactly(2))
            ->method('getAllStateChangesForCommit')
            ->willReturn([]);

        $cachingEventStoreService = new CachingEventStoreService($mockEventStoreService);

        $this->assertEquals(
            [],
            $cachingEventStoreService->getAllStateChangesForCommit('', '')
        );
        $this->assertEquals(
            [],
            $cachingEventStoreService->getAllStateChangesForCommit('', '')
        );
    }

    public function testStoreStateChange(): void
    {
        $mockOrchestratedEvent = $this->createMock(OrchestratedEventInterface::class);
        $mockStateChange = new EventStateChange(
            provider: Provider::GITHUB,
            identifier: '',
            owner: '',
            repository: '',
            version: 1,
            event: [],
        );

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
        $mockEventStoreService->expects($this->exactly(2))
            ->method('storeStateChange')
            ->willReturn($mockStateChange);

        $cachingEventStoreService = new CachingEventStoreService($mockEventStoreService);

        $this->assertEquals(
            $mockStateChange,
            $cachingEventStoreService->storeStateChange($mockOrchestratedEvent)
        );
        $this->assertEquals(
            $mockStateChange,
            $cachingEventStoreService->storeStateChange($mockOrchestratedEvent)
        );
    }
}
