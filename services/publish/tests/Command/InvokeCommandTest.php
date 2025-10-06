<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Handler\EventHandler;
use Bref\Event\Sqs\SqsHandler;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class InvokeCommandTest extends KernelTestCase
{
    public function testInvoke(): void
    {
        $mockEventHandler = $this->createMock(SqsHandler::class);
        $mockEventHandler->expects($this->once())
            ->method('handleSqs');

        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $kernel->getContainer()->set(EventHandler::class, $mockEventHandler);

        $command = $application->find('app:invoke');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
    }
}
