<?php

namespace App\Tests\Command;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\AuthTokenService;
use Packages\Contracts\Provider\Provider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class NewProjectCommandTest extends KernelTestCase
{
    public function testCreatingNewProject(): void
    {
        $mockProjectRepo = $this->createMock(ProjectRepository::class);

        $mockProjectRepo->expects($this->once())
            ->method('save')
            ->with(
                self::callback(
                    static fn (Project $project) => $project->getOwner() === 'mock-owner' &&
                        $project->getRepository() === 'mock-repository' &&
                        $project->getProvider() === Provider::GITHUB &&
                        $project->getGraphToken() === 'mock-graph-token' &&
                        $project->getUploadToken() === 'mock-upload-token' &&
                        $project->getId() === null &&
                        $project->getCoveragePercentage() === null &&
                        $project->isEnabled() === true
                ),
                true
            );

        $mockAuthTokenService = $this->createMock(AuthTokenService::class);

        $mockAuthTokenService->expects($this->once())
            ->method('createNewGraphToken')
            ->willReturn('mock-graph-token');

        $mockAuthTokenService->expects($this->once())
            ->method('createNewUploadToken')
            ->willReturn('mock-upload-token');

        $kernel = self::bootKernel();
        $application = new Application($kernel);

        static::getContainer()->set(AuthTokenService::class, $mockAuthTokenService);
        static::getContainer()->set(ProjectRepository::class, $mockProjectRepo);

        $command = $application->find('app:new_project');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'owner' => 'mock-owner',
            'repository' => 'mock-repository',
            'provider' => Provider::GITHUB->value,
        ]);

        $commandTester->assertCommandIsSuccessful();
    }
}
