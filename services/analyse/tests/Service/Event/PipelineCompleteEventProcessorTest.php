<?php

namespace App\Tests\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\PublishableCoverageDataInterface;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Service\CoverageAnalyserService;
use App\Service\Event\PipelineCompleteEventProcessor;
use Bref\Event\EventBridge\EventBridgeEvent;
use DateTimeImmutable;
use DateTimeInterface;
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
        $event = PipelineComplete::from([
            'provider' => Provider::GITHUB->value,
            'commit' => 'mock-commit',
            'repository' => 'mock-repository',
            'owner' => 'mock-owner',
            'ref' => 'mock-ref',
            'pullRequest' => 'mock-pull-request',
            'completedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM)
        ]);

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
                    function (PublishableCheckRunMessage $message) use ($event) {
                        $this->assertEquals(
                            [
                                1 => PublishableCheckAnnotationMessage::from(
                                    [
                                        'event' => $event->jsonSerialize(),
                                        'fileName' => 'mock-path/mock-file-2.php',
                                        'lineNumber' => 2,
                                        'lineState' => LineState::UNCOVERED->value,
                                        'validUntil' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                                    ]
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
            $mockCoverageAnalysisService,
            $mockSqsMessageClient,
            $mockEventBridgeEventClient
        );

        $pipelineCompleteEventProcessor->process(
            new EventBridgeEvent([
                'detail-type' => CoverageEvent::PIPELINE_COMPLETE->value,
                'detail' => $event->jsonSerialize()
            ])
        );
    }
}
