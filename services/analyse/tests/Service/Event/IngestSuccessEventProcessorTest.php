<?php

namespace App\Tests\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Model\PublishableCoverageDataInterface;
use App\Service\CoverageAnalyserService;
use App\Service\Event\IngestSuccessEventProcessor;
use Bref\Event\EventBridge\EventBridgeEvent;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\PublishableMessage\PublishablePullRequestMessage;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class IngestSuccessEventProcessorTest extends KernelTestCase
{
    public function testProcess(): void
    {
        $upload = [
            'provider' => Provider::GITHUB->value,
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'ref' => 'mock-ref',
            'commit' => 'mock-commit',
            'parent' => ['mock-parent'],
            'ingestTime' => '2021-01-01T00:00:00+00:00',
            'uploadId' => 'mock-upload-id',
            'pullRequest' => 'mock-pull-request',
            'tag' => [
                'name' => 'mock-tag',
                'commit' => 'mock-commit',
            ],
            'eventTime' => '2021-01-01T00:00:00+00:00',
            'projectRoot' => 'mock-project-root',
        ];

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
                    function (PublishablePullRequestMessage $message) {
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

        $ingestSuccessEventProcessor = new IngestSuccessEventProcessor(
            new NullLogger(),
            $this->getContainer()->get(SerializerInterface::class),
            $mockCoverageAnalysisService,
            $mockSqsMessageClient,
            $mockEventBridgeEventClient,
        );

        $ingestSuccessEventProcessor->process(
            new EventBridgeEvent([
                'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                'detail' => $upload
            ])
        );
    }
}
