<?php

namespace App\Tests\Command;

use App\Handler\EventHandler;
use Bref\Event\InvalidLambdaEvent;
use Packages\Event\Enum\Event;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class InvokeEventCommandTest extends KernelTestCase
{
    public function testInvokeSuccessfully(): void
    {
        $mockEventHandler = $this->createMock(EventHandler::class);

        $mockEventHandler->expects($this->once())
            ->method('handleEventBridge');

        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $kernel->getContainer()->set(EventHandler::class, $mockEventHandler);

        $command = $application->find('app:invoke_event');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'event' => Event::COVERAGE_FINALISED->value,
            'body' => '["mock-event-detail"]'
        ]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testInvokeFailure(): void
    {
        $mockHandler = $this->createMock(EventHandler::class);

        $mockHandler->expects($this->once())
            ->method('handleEventBridge')
            ->willThrowException(new InvalidLambdaEvent('eventbridge', ''));

        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $kernel->getContainer()->set(EventHandler::class, $mockHandler);

        $command = $application->find('app:invoke');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'event' => Event::COVERAGE_FINALISED->value,
            'body' => '["mock-event-detail"]'
        ]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Instead, the handler was invoked with invalid event data: null', $output);
    }
}
