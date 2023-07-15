<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CommitTreeBuilderCommandTest extends KernelTestCase
{
    public function testInvokeSuccessfully(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('app:tree_builder');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }
}
