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
use Packages\Models\Enum\PublishableCheckRunStatus;
use Packages\Models\Model\Event\PipelineComplete;
use Packages\Models\Model\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class PipelineCompleteEventProcessorTest extends KernelTestCase
{
    public function testProcess(): void
    {
        $validUntil = new DateTimeImmutable('2021-01-01T00:00:00+00:00');

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
        $mockPublishableCoverageData->expects($this->atLeastOnce())
            ->method('getLatestSuccessfulUpload')
            ->willReturn($validUntil);
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
                            PublishableCheckRunStatus::SUCCESS,
                            $message->getStatus()
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
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageAnalysisService,
            $mockSqsMessageClient,
            $mockEventBridgeEventClient
        );

        $pipelineCompleteEventProcessor->process(
            new EventBridgeEvent([
                'detail-type' => CoverageEvent::PIPELINE_COMPLETE->value,
                'detail' => [
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository',
                    'ref' => 'mock-ref',
                    'commit' => 'mock-commit',
                    'pullRequest' => null,
                    'completedAt' => $validUntil->format(DateTimeInterface::ATOM),
                    'validUntil' => $validUntil->format(DateTimeInterface::ATOM)
                ]
            ])
        );
    }
}
