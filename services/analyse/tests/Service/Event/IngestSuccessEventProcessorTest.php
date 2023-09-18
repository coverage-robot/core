<?php

namespace App\Tests\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\PublishableCoverageDataInterface;
use App\Service\CoverageAnalyserService;
use App\Service\Event\IngestSuccessEventProcessor;
use App\Tests\Mock\Factory\MockSerializerFactory;
use Bref\Event\EventBridge\EventBridgeEvent;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\PublishableMessage\PublishableMessageCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class IngestSuccessEventProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $mockUpload = $this->createMock(Upload::class);

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
                    function (PublishableMessageCollection $message) {
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
            MockSerializerFactory::getMock(
                $this,
                serializeMap: [
                    [
                        $mockUpload,
                        'json',
                        [],
                        'mock-upload'
                    ]
                ],
                deserializeMap: [
                    [
                        'mock-upload',
                        Upload::class,
                        'json',
                        [],
                        $mockUpload
                    ]
                ]
            ),
            $mockCoverageAnalysisService,
            $mockSqsMessageClient,
            $mockEventBridgeEventClient,
        );

        $ingestSuccessEventProcessor->process(
            new EventBridgeEvent([
                'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                'detail' => 'mock-upload'
            ])
        );
    }
}
