<?php

namespace App\Tests\Event;

use App\Event\Backoff\EventStoreRecorderBackoffStrategy;
use App\Event\CoverageFailedEventProcessor;
use App\Model\EventStateChange;
use App\Service\EventStoreServiceInterface;
use App\Tests\Mock\FakeBackoffStrategy;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Model\CoverageFailed;
use Packages\Message\Client\SqsClientInterface;
use Packages\Message\PublishableMessage\PublishableCoverageFailedJobMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CoverageFailedEventProcessorTest extends TestCase
{
    public function testPublishingMessageBasedOnFailedCoverage(): void
    {
        $event = new CoverageFailed(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'main',
            commit: 'mock-commit'
        );

        $mockPublishClient = $this->createMock(SqsClientInterface::class);
        $mockPublishClient->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PublishableCoverageFailedJobMessage::class))
            ->willReturn(true);

        $mockEventStoreService = $this->createMock(EventStoreServiceInterface::class);
        $mockEventStoreService->expects($this->once())
            ->method('storeStateChange')
            ->willReturn(
                new EventStateChange(
                    Provider::GITHUB,
                    'mock-identifier',
                    'mock-owner',
                    'mock-repository',
                    2,
                    []
                )
            );

        $coverageFailedEventProcessor = new CoverageFailedEventProcessor(
            $mockEventStoreService,
            new NullLogger(),
            new FakeBackoffStrategy(EventStoreRecorderBackoffStrategy::class),
            $mockPublishClient
        );

        $this->assertTrue(
            $coverageFailedEventProcessor->process($event)
        );
    }
}
