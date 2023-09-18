<?php

namespace App\Tests\Command;

use App\Handler\EventHandler;
use Bref\Event\InvalidLambdaEvent;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class InvokeCommandTest extends KernelTestCase
{
    public function testInvokeSuccessfully(): void
    {
        $mockEventHandler = $this->createMock(EventHandler::class);

        $mockEventHandler->expects($this->once())
            ->method('handleEventBridge');

        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $kernel->getContainer()->set(EventHandler::class, $mockEventHandler);

        $command = $application->find('app:invoke');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'commit' => 'mock-commit',
            'parent' => ['mock-parent'],
            'owner' => 'mock-owner',
            'tag' => 'mock-tag',
            'repository' => 'mock-repository',
            'pullRequest' => 'mock-pull-request'
        ]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testInvokeFailure(): void
    {
        $mockAnalyseHandler = $this->createMock(EventHandler::class);

        $mockAnalyseHandler->expects($this->once())
            ->method('handleEventBridge')
            ->willThrowException(new InvalidLambdaEvent('sqs', ''));

        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $kernel->getContainer()->set(EventHandler::class, $mockAnalyseHandler);

        $command = $application->find('app:invoke');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'commit' => 'mock-commit',
            'parent' => ['mock-parent'],
            'owner' => 'mock-owner',
            'tag' => 'mock-tag',
            'repository' => 'mock-repository',
            'pullRequest' => 'mock-pull-request'
        ]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Instead, the handler was invoked with invalid event data: null', $output);
    }
}
