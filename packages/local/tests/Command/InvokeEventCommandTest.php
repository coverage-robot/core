<?php

namespace Packages\Local\Tests\Command;

use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Handler\EventHandler;
use Packages\Event\Model\EventInterface;
use Packages\Event\Model\UploadsFinalised;
use Packages\Local\Command\InvokeEventCommand;
use Packages\Local\Service\EventBuilderInterface;
use Packages\Local\Tests\Mock\FakeEventBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

final class InvokeEventCommandTest extends TestCase
{
    public function testInvokingEventUsingBuilders(): void
    {
        $mockSerializer = $this->createMock(Serializer::class);
        $mockSerializer->expects($this->once())
            ->method('normalize')
            ->with(
                new UploadsFinalised(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    parent: [],
                    eventTime: new DateTimeImmutable('2021-01-01T00:00:00+00:00')
                )
            )
            ->willReturn(['mock-serialized-event']);

        $mockEventHandler = $this->createMock(EventBridgeHandler::class);
        $mockEventHandler->expects($this->once())
            ->method('handleEventBridge')
            ->with(
                new EventBridgeEvent([
                    'detail-type' => Event::UPLOADS_FINALISED->value,
                    'detail' => ['mock-serialized-event']
                ])
            );

        $commandTester = new CommandTester(
            new InvokeEventCommand(
                $mockSerializer,
                $mockEventHandler,
                [
                    new FakeEventBuilder(),
                    $this->createMock(EventBuilderInterface::class)
                ]
            )
        );

        $outcome = $commandTester->execute([
            'event' => Event::UPLOADS_FINALISED->value
        ]);

        $this->assertEquals(
            Command::SUCCESS,
            $outcome
        );
    }
}
