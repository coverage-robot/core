<?php

declare(strict_types=1);

namespace Packages\Event\Tests\Service;

use DateTimeImmutable;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\Upload;
use Packages\Event\Processor\EventProcessorInterface;
use Packages\Event\Service\EventProcessorService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class EventProcessorServiceTest extends TestCase
{
    public function testProcessEventSuccessfully(): void
    {
        $event = new IngestSuccess(
            new Upload(
                uploadId: 'mock-upload-id',
                provider: Provider::GITHUB,
                projectId: 'mock-project-id',
                owner: 'mock-owner',
                repository: 'mock-repository',
                commit: 'mock-commit',
                parent: [],
                ref: 'mock-ref',
                projectRoot: '',
                tag: new Tag('mock-tag', 'mock-commit', [31]),
            ),
            new DateTimeImmutable()
        );

        $mockProcessor = $this->createMock(EventProcessorInterface::class);
        $mockProcessor->expects($this->once())
            ->method('process')
            ->with($event)
            ->willReturn(true);

        $eventProcessor = new EventProcessorService(
            new NullLogger(),
            [
                Event::INGEST_SUCCESS->value => $mockProcessor,
                Event::INGEST_FAILURE->value => $this->createMock(EventProcessorInterface::class),
            ]
        );

        $this->assertTrue(
            $eventProcessor->process(
                Event::INGEST_SUCCESS,
                $event
            )
        );
    }

    public function testProcessUnsupportedEvent(): void
    {
        $event = new IngestSuccess(
            new Upload(
                uploadId: 'mock-upload-id',
                provider: Provider::GITHUB,
                projectId: 'mock-project-id',
                owner: 'mock-owner',
                repository: 'mock-repository',
                commit: 'mock-commit',
                parent: [],
                ref: 'mock-ref',
                projectRoot: '',
                tag: new Tag('mock-tag', 'mock-commit', [32]),
            ),
            new DateTimeImmutable()
        );

        $mockProcessor = $this->createMock(EventProcessorInterface::class);
        $mockProcessor->expects($this->never())
            ->method('process');

        $eventProcessor = new EventProcessorService(
            new NullLogger(),
            [
                Event::JOB_STATE_CHANGE->value => $mockProcessor,
            ]
        );

        $this->expectException(RuntimeException::class);

        $eventProcessor->process(
            Event::INGEST_SUCCESS,
            $event
        );
    }
}
