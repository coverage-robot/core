<?php

namespace App\Tests\Command;

use App\Handler\AnalyseHandler;
use Bref\Event\InvalidLambdaEvent;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class InvokeCommandTest extends KernelTestCase
{
    public function testInvokeSuccessfully(): void
    {
        $mockIngestHandler = $this->createMock(AnalyseHandler::class);

        $mockIngestHandler->expects($this->once())
            ->method('handleSqs');

        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $kernel->getContainer()->set(AnalyseHandler::class, $mockIngestHandler);

        $command = $application->find('app:invoke');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'commit' => 'mock-commit',
            'parent' => 'mock-parent',
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'pullRequest' => 'mock-pull-request',
        ]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testInvokeFailure(): void
    {
        $mockIngestHandler = $this->createMock(AnalyseHandler::class);

        $mockIngestHandler->expects($this->once())
            ->method('handleSqs')
            ->willThrowException(new InvalidLambdaEvent('sqs', ''));

        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $kernel->getContainer()->set(AnalyseHandler::class, $mockIngestHandler);

        $command = $application->find('app:invoke');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'commit' => 'mock-commit',
            'parent' => 'mock-parent',
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'pullRequest' => 'mock-pull-request',
        ]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Instead, the handler was invoked with invalid event data: null', $output);
    }
}
