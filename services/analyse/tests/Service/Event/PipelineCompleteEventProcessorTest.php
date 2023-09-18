<?php

namespace App\Tests\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\PublishableCoverageDataInterface;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Service\CoverageAnalyserService;
use App\Service\Event\PipelineCompleteEventProcessor;
use App\Tests\Mock\Factory\MockSerializerFactory;
use Bref\Event\EventBridge\EventBridgeEvent;
use DateTimeImmutable;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\LineState;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\PipelineComplete;
use Packages\Models\Model\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PipelineCompleteEventProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $validUntil = new DateTimeImmutable();

        $event = new PipelineComplete(
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-ref',
            'mock-commit',
            null,
            $validUntil
        );

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);
        $mockPublishableCoverageData->expects($this->atLeastOnce())
            ->method('getCoveragePercentage')
            ->willReturn(100.0);
        $mockPublishableCoverageData->expects($this->once())
            ->method('getDiffLineCoverage')
            ->willReturn(
                LineCoverageCollectionQueryResult::from(
                    [
                        [
                            'lineNumber' => 1,
                            'state' => LineState::COVERED->value,
                            'fileName' => 'mock-path/mock-file.php',
                        ],
                        [
                            'lineNumber' => 2,
                            'state' => LineState::UNCOVERED->value,
                            'fileName' => 'mock-path/mock-file-2.php',
                        ]
                    ]
                )
            );

        $mockCoverageAnalysisService = $this->createMock(CoverageAnalyserService::class);
        $mockCoverageAnalysisService->expects($this->once())
            ->method('analyse')
            ->willReturn($mockPublishableCoverageData);

        $mockSqsMessageClient = $this->createMock(SqsMessageClient::class);
        $mockSqsMessageClient->expects($this->once())
            ->method('queuePublishableMessage')
            ->with(
                self::callback(
                    function (PublishableCheckRunMessage $message) use ($event, $validUntil) {
                        $this->assertEquals(
                            [
                                1 => new PublishableCheckAnnotationMessage(
                                    $event,
                                    'mock-path/mock-file-2.php',
                                    2,
                                    LineState::UNCOVERED,
                                    $validUntil
                                )
                            ],
                            $message->getAnnotations()
                        );
                        $this->assertEquals(
                            100,
                            $message->getCoveragePercentage()
                        );
                        return true;
                    }
                )
            )
            ->willReturn(true);

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);

        $pipelineCompleteEventProcessor = new PipelineCompleteEventProcessor(
            new NullLogger(),
            MockSerializerFactory::getMock(
                $this,
                serializeMap: [
                    [
                        $event,
                        'json',
                        [],
                        'mock-pipeline-complete'
                    ]
                ],
                deserializeMap: [
                    [
                        'mock-pipeline-complete',
                        PipelineComplete::class,
                        'json',
                        [],
                        $event
                    ]
                ]
            ),
            $mockCoverageAnalysisService,
            $mockSqsMessageClient,
            $mockEventBridgeEventClient
        );

        $pipelineCompleteEventProcessor->process(
            new EventBridgeEvent([
                'detail-type' => CoverageEvent::PIPELINE_COMPLETE->value,
                'detail' => 'mock-pipeline-complete'
            ])
        );
    }
}
