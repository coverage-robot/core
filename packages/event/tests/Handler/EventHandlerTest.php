<?php

namespace App\Tests\Handler;

use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Handler\EventHandler;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\Upload;
use Packages\Event\Service\EventProcessorServiceInterface;
use Packages\Event\Service\EventValidationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class EventHandlerTest extends TestCase
{
    public function testHandlingEvent(): void
    {
        $serializedEvent = json_encode([
            'type' => Event::UPLOAD->value,
            'uploadId' => 'mock-uuid',
            'provider' => Provider::GITHUB->value,
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'commit' => 'mock-commit',
            'parent' => [],
            'ref' => 'mock-ref',
            'projectRoot' => '',
            'tag' => ['tag' => '1', 'commit' => 'mock-commit'],
            'pullRequest' => null,
            'baseCommit' => null,
            'baseRef' => null
        ]);

        $mockUpload = $this->createMock(EventInterface::class);

        $mockEventProcessor = $this->createMock(EventProcessorServiceInterface::class);
        $mockEventProcessor->expects($this->once())
            ->method('process')
            ->with(
                Event::UPLOAD,
                $mockUpload
            )
            ->willReturn(true);

        $mockSerializer = $this->createMock(Serializer::class);
        $mockSerializer->expects($this->once())
            ->method('denormalize')
            ->with($serializedEvent, EventInterface::class)
            ->willReturn($mockUpload);

        $mockValidator = $this->createMock(ValidatorInterface::class);
        $mockValidator->expects($this->once())
            ->method('validate')
            ->with($mockUpload);

        $eventHandler = new EventHandler(
            $mockEventProcessor,
            $mockSerializer,
            new EventValidationService($mockValidator)
        );

        $eventHandler->handleEventBridge(
            new EventBridgeEvent([
                'detail-type' => Event::UPLOAD->value,
                'detail' => $serializedEvent
            ]),
            Context::fake()
        );
    }
}
