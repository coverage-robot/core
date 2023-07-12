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
        $mockIngestHandler = $this->createMock(EventHandler::class);

        $mockIngestHandler->expects($this->once())
            ->method('handleS3');

        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $kernel->getContainer()->set(EventHandler::class, $mockIngestHandler);

        $command = $application->find('app:invoke');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'key' => 'mock-file.xml',
        ]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testInvokeFailure(): void
    {
        $mockIngestHandler = $this->createMock(EventHandler::class);

        $mockIngestHandler->expects($this->once())
            ->method('handleS3')
            ->willThrowException(new InvalidLambdaEvent('s3', ''));

        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $kernel->getContainer()->set(EventHandler::class, $mockIngestHandler);

        $command = $application->find('app:invoke');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'key' => 'mock-file.xml',
        ]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Instead, the handler was invoked with invalid event data: null', $output);
    }
}
