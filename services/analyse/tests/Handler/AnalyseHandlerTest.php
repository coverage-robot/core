<?php

namespace App\Tests\Handler;

use App\Enum\ProviderEnum;
use App\Handler\AnalyseHandler;
use App\Model\PublishableCoverageDataInterface;
use App\Model\Upload;
use App\Service\CoverageAnalyserService;
use App\Service\CoveragePublisherService;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AnalyseHandlerTest extends TestCase
{
    public function testHandleSqs(): void
    {
        $body = [
            'uploadId' => 'mock-uuid',
            'provider' => ProviderEnum::GITHUB->value,
            'commit' => 'mock-commit',
            'parent' => 'mock-parent-commit',
            'owner' => 'mock-owner',
            'repository' => 'mock-repository'
        ];

        $upload = new Upload($body);

        $mockPublishableCoverageData = $this->createMock(PublishableCoverageDataInterface::class);

        $mockCoverageAnalyserService = $this->createMock(CoverageAnalyserService::class);

        $mockCoverageAnalyserService->expects($this->once())
            ->method('analyse')
            ->with($upload)
            ->willReturn($mockPublishableCoverageData);

        $mockCoveragePublisherService = $this->createMock(CoveragePublisherService::class);

        $mockCoveragePublisherService->expects($this->once())
            ->method('publish')
            ->with($upload, $mockPublishableCoverageData)
            ->willReturn(true);

        $handler = new AnalyseHandler(new NullLogger(), $mockCoverageAnalyserService, $mockCoveragePublisherService);

        $handler->handleSqs(
            new SqsEvent(
                [
                    'Records' => [
                        [
                            'eventSource' => 'aws:sqs',
                            'messageId' => 'mock',
                            'body' => json_encode($body),
                            'messageAttributes' => []
                        ]
                    ]
                ]
            ),
            Context::fake()
        );
    }
}
