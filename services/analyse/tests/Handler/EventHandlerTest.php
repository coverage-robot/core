<?php

namespace App\Tests\Handler;

use App\Handler\EventHandler;
use App\Service\Event\IngestSuccessEventProcessor;
use App\Service\Event\PipelineCompleteEventProcessor;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use DateTimeImmutable;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\EventInterface;
use Packages\Models\Model\Event\PipelineComplete;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

class EventHandlerTest extends TestCase
{
    public function testHandleUploadEvent(): void
    {
        $upload = new Upload(
            'mock-uuid',
            Provider::GITHUB,
            'mock-owner',
            'mock-repository',
            'mock-commit',
            ['mock-parent-commit'],
            'mock-ref',
            'mock-project-root',
            null,
            new Tag('mock-tag', 'mock-commit'),
            new DateTimeImmutable()
        );

        $mockContainer = $this->createMock(ContainerInterface::class);

        $mockProcessor = $this->createMock(IngestSuccessEventProcessor::class);

        $mockProcessor->expects($this->once())
            ->method('process');

        $mockContainer->expects($this->once())
            ->method('get')
            ->with(IngestSuccessEventProcessor::class)
            ->willReturn($mockProcessor);

        $handler = new EventHandler(
            new NullLogger(),
            $mockContainer
        );

        $handler->handleEventBridge(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::INGEST_SUCCESS->value,
                    'detail' => [
                        'uploadId' => $upload->getUploadId(),
                        'provider' => $upload->getProvider()->value,
                        'owner' => $upload->getOwner(),
                        'repository' => $upload->getRepository(),
                        'commit' => $upload->getCommit(),
                        'parent' => $upload->getParent(),
                        'ref' => $upload->getRef(),
                        'projectRoot' => $upload->getProjectRoot(),
                        'pullRequest' => $upload->getPullRequest(),
                        'tag' => [
                            'name' => $upload->getTag()->getName(),
                            'commit' => $upload->getTag()->getCommit()
                        ],
                        'ingestTime' => $upload->getIngestTime()->format(DateTimeImmutable::ATOM)
                    ]
                ]
            ),
            Context::fake()
        );
    }

    public function testHandlePipelineCompleteEvent(): void
    {
        $pipelineCompleteEvent = new PipelineComplete(
            Provider::GITHUB,
            'mock-commit',
            'mock-owner',
            'mock-ref',
            'mock-repository',
            null,
            new DateTimeImmutable()
        );

        $mockContainer = $this->createMock(ContainerInterface::class);

        $mockProcessor = $this->createMock(PipelineCompleteEventProcessor::class);

        $mockProcessor->expects($this->once())
            ->method('process');

        $mockContainer->expects($this->once())
            ->method('get')
            ->with(PipelineCompleteEventProcessor::class)
            ->willReturn($mockProcessor);

        $handler = new EventHandler(
            new NullLogger(),
            $mockContainer
        );

        $handler->handleEventBridge(
            new EventBridgeEvent(
                [
                    'detail-type' => CoverageEvent::PIPELINE_COMPLETE->value,
                    'detail' => [
                        'provider' => $pipelineCompleteEvent->getProvider()->value,
                        'commit' => $pipelineCompleteEvent->getCommit(),
                        'owner' => $pipelineCompleteEvent->getOwner(),
                        'ref' => $pipelineCompleteEvent->getRef(),
                        'repository' => $pipelineCompleteEvent->getRepository(),
                        'completedAt' => $pipelineCompleteEvent->getCompletedAt()->format(DateTimeImmutable::ATOM),
                    ]
                ]
            ),
            Context::fake()
        );
    }

    public function testHandleInvalidEvent(): void
    {
        $mockContainer = $this->createMock(ContainerInterface::class);

        $mockContainer->expects($this->never())
            ->method('get');

        $handler = new EventHandler(
            new NullLogger(),
            $mockContainer
        );

        $handler->handleEventBridge(
            new EventBridgeEvent(
                [
                    'detail-type' => 'some-other-event-type',
                    'detail' => $this->createMock(EventInterface::class)
                ]
            ),
            Context::fake()
        );
    }

    public function testSubscribedServices(): void
    {
        $this->assertEquals(
            [
                IngestSuccessEventProcessor::class,
                PipelineCompleteEventProcessor::class
            ],
            EventHandler::getSubscribedServices()
        );
    }
}
