<?php

namespace Packages\Event\Tests\Service;

use DateTimeImmutable;
use Packages\Event\Enum\Event;
use Packages\Event\Model\IngestSuccess;
use Packages\Event\Model\Upload;
use Packages\Event\Processor\EventProcessorInterface;
use Packages\Event\Service\EventProcessorService;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Tag;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

class EventProcessorServiceTest extends TestCase
{
    public function testProcessEventSuccessfully(): void
    {
        $event = new IngestSuccess(
            new Upload(
                'mock-upload-id',
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-commit',
                [],
                'mock-ref',
                '',
                null,
                new Tag('mock-tag', 'mock-commit'),
                new DateTimeImmutable()
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
                'mock-upload-id',
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-commit',
                [],
                'mock-ref',
                '',
                null,
                new Tag('mock-tag', 'mock-commit'),
                new DateTimeImmutable()
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
