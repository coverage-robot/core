<?php

namespace App\Tests\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\PublishableCoverageDataInterface;
use App\Service\CoverageAnalyserService;
use App\Service\Event\IngestSuccessEventProcessor;
use Bref\Event\EventBridge\EventBridgeEvent;
use DateTimeImmutable;
use DateTimeInterface;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\PublishableMessage\PublishableMessageCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class IngestSuccessEventProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $event = Upload::from(
            [
                'uploadId' => 'mock-uuid',
                'provider' => Provider::GITHUB->value,
                'commit' => 'mock-commit',
                'parent' => '["mock-parent-commit"]',
                'owner' => 'mock-owner',
                'tag' => 'mock-tag',
                'ref' => 'mock-ref',
                'repository' => 'mock-repository',
                'ingestTime' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            ]
        );

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);
        $mockPublishableCoverageData->expects($this->atLeastOnce())
            ->method('getCoveragePercentage')
            ->willReturn(100.0);

        $mockCoverageAnalysisService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalysisService->expects($this->once())
            ->method('analyse')
            ->willReturn($mockPublishableCoverageData);

        $mockSqsMessageClient = $this->createMock(SqsMessageClient::class);
        $mockSqsMessageClient->expects($this->once())
            ->method('queuePublishableMessage')
            ->with(
                self::callback(
                    function (PublishableMessageCollection $message) use ($event) {
                        $this->assertCount(
                            2,
                            $message->getMessages()
                        );
                        return true;
                    }
                )
            )
            ->willReturn(true);

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);

        $ingestSuccessEventProcessor = new IngestSuccessEventProcessor(
            new NullLogger(),
            $mockCoverageAnalysisService,
            $mockSqsMessageClient,
            $mockEventBridgeEventClient
        );

        $ingestSuccessEventProcessor->process(
            new EventBridgeEvent([
                'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                'detail' => $event->jsonSerialize()
            ])
        );
    }
}
