<?php

namespace App\Tests\Event;

use App\Enum\OrchestratedEventState;
use App\Event\CoverageFinalisedEventProcessor;
use App\Model\EventStateChange;
use App\Model\EventStateChangeCollection;
use App\Model\Finalised;
use App\Service\EventStoreServiceInterface;
use App\Tests\Mock\FakeEventStoreRecorderBackoffStrategy;
use DateTimeImmutable;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\CoverageFinalised;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CoverageFinalisedEventProcessorTest extends TestCase
{
    public function testGetEvent(): void
    {
        $this->assertEquals(
            Event::COVERAGE_FINALISED->value,
            CoverageFinalisedEventProcessor::getEvent()
        );
    }

    public function testProcessingFinalisedEvent(): void
    {
        $eventTime = new DateTimeImmutable();

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
        $mockEventStoreService->expects($this->once())
            ->method('getAllStateChangesForEvent')
            ->willReturn(new EventStateChangeCollection([]));
        $mockEventStoreService->expects($this->once())
            ->method('storeStateChange')
            ->with(
                new Finalised(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    state: OrchestratedEventState::SUCCESS,
                    pullRequest: null,
                    eventTime: $eventTime
                )
            )
            ->willReturn($this->createMock(EventStateChange::class));

        $coverageFinalisedEventProcessor = new CoverageFinalisedEventProcessor(
            $mockEventStoreService,
            new NullLogger(),
            new FakeEventStoreRecorderBackoffStrategy()
        );

        $coverageFinalisedEventProcessor->process(
            new CoverageFinalised(
                provider: Provider::GITHUB,
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                coveragePercentage: 100.0,
                eventTime: $eventTime
            )
        );
    }
}
