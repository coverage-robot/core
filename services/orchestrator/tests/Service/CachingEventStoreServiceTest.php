<?php

namespace App\Tests\Service;

use App\Model\EventStateChangeCollection;
use App\Model\OrchestratedEventInterface;
use App\Service\CachingEventStoreService;
use App\Service\EventStoreService;
use PHPUnit\Framework\TestCase;

class CachingEventStoreServiceTest extends TestCase
{
    public function testReducingStateChangesToEventUsesCache(): void
    {
        $mockCollection = $this->createMock(EventStateChangeCollection::class);
        $mockOrchestratedEvent = $this->createMock(OrchestratedEventInterface::class);

        $mockEventStoreService = $this->createMock(EventStoreService::class);
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

    public function testGetStateChangeForEvent(): void
    {
        $mockOrchestratedEvent = $this->createMock(OrchestratedEventInterface::class);

        $mockEventStoreService = $this->createMock(EventStoreService::class);
        $mockEventStoreService->expects($this->exactly(2))
            ->method('getStateChangeForEvent')
            ->willReturn([]);

        $cachingEventStoreService = new CachingEventStoreService($mockEventStoreService);

        $this->assertEquals(
            [],
            $cachingEventStoreService->getStateChangeForEvent(null, $mockOrchestratedEvent)
        );
        $this->assertEquals(
            [],
            $cachingEventStoreService->getStateChangeForEvent(null, $mockOrchestratedEvent)
        );
    }
}
