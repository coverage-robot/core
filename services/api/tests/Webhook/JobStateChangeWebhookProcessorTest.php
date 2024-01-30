<?php

namespace App\Tests\Webhook;

use App\Client\EventBridgeEventClient;
use App\Entity\Job;
use App\Entity\Project;
use App\Enum\EnvironmentVariable;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Repository\JobRepository;
use App\Webhook\JobStateChangeWebhookProcessor;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Event\Client\EventBusClient;
use Packages\Event\Client\EventBusClientInterface;
use Packages\Event\Enum\JobState;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class JobStateChangeWebhookProcessorTest extends TestCase
{
    public function testProcessingWebhookCreatingNewJobForProject(): void
    {
        $mockProject = $this->createMock(Project::class);
        $newJob = $this->createMock(Job::class);

        $mockJobRepository = $this->createMock(JobRepository::class);
        $mockJobRepository->expects($this->exactly(1))
            ->method('findOneBy')
            ->with(
                [
                    'project' => $mockProject,
                    'externalId' => '1',
                    'commit' => 'mock-commit'
                ]
            )
            ->willReturn(null);
        $mockJobRepository->expects($this->once())
            ->method('create')
            ->willReturn($newJob);
        $mockJobRepository->expects($this->once())
            ->method('save');

        $mockEventBusClient = $this->createMock(EventBusClientInterface::class);
        $mockEventBusClient->expects($this->once())
            ->method('fireEvent');

        $jobStateChangeWebhookProcessor = new JobStateChangeWebhookProcessor(
            new NullLogger(),
            $mockJobRepository,
            $mockEventBusClient,
            MockEnvironmentServiceFactory::createMock(
                $this,
                Environment::PRODUCTION,
                [
                    EnvironmentVariable::GITHUB_APP_ID->value => 'mock-app-id',
                ]
            )
        );

        $jobStateChangeWebhookProcessor->process(
            $mockProject,
            new GithubCheckRunWebhook(
                '',
                'mock-owner',
                'mock-repository',
                '1',
                1,
                'mock-ref',
                'mock-commit',
                'mock-parent-commit',
                null,
                null,
                null,
                JobState::COMPLETED
            )
        );
    }

    public function testProcessingWebhookUpdatingExistingJobForProject(): void
    {
        $mockProject = $this->createMock(Project::class);
        $job = $this->createMock(Job::class);

        $mockJobRepository = $this->createMock(JobRepository::class);
        $mockJobRepository->expects($this->once())
            ->method('findOneBy')
            ->with(
                [
                    'project' => $mockProject,
                    'externalId' => '1',
                    'commit' => 'mock-commit'
                ]
            )
            ->willReturn($job);
        $mockJobRepository->expects($this->never())
            ->method('create');
        $mockJobRepository->expects($this->once())
            ->method('save');

        $mockEventBusClient = $this->createMock(EventBusClientInterface::class);
        $mockEventBusClient->expects($this->once())
            ->method('fireEvent');

        $jobStateChangeWebhookProcessor = new JobStateChangeWebhookProcessor(
            new NullLogger(),
            $mockJobRepository,
            $mockEventBusClient,
            MockEnvironmentServiceFactory::createMock(
                $this,
                Environment::PRODUCTION,
                [
                    EnvironmentVariable::GITHUB_APP_ID->value => 'mock-app-id',
                ]
            )
        );

        $jobStateChangeWebhookProcessor->process(
            $mockProject,
            new GithubCheckRunWebhook(
                '',
                'mock-owner',
                'mock-repository',
                '1',
                1,
                'mock-ref',
                'mock-commit',
                'mock-parent-commit',
                null,
                null,
                null,
                JobState::COMPLETED
            )
        );
    }

    public function testProcessingWebhookForStateChangeTriggeredInternally(): void
    {
        $mockProject = $this->createMock(Project::class);

        $mockJobRepository = $this->createMock(JobRepository::class);
        $mockJobRepository->expects($this->never())
            ->method('findOneBy');
        $mockJobRepository->expects($this->never())
            ->method('save');

        $mockEventBusClient = $this->createMock(EventBusClientInterface::class);
        $mockEventBusClient->expects($this->never())
            ->method('fireEvent');

        $jobStateChangeWebhookProcessor = new JobStateChangeWebhookProcessor(
            new NullLogger(),
            $mockJobRepository,
            $mockEventBusClient,
            MockEnvironmentServiceFactory::createMock(
                $this,
                Environment::PRODUCTION,
                [
                    EnvironmentVariable::GITHUB_APP_ID->value => 'mock-app-id',
                ]
            )
        );

        $jobStateChangeWebhookProcessor->process(
            $mockProject,
            new GithubCheckRunWebhook(
                '',
                'mock-owner',
                'mock-repository',
                '1',
                'mock-app-id',
                'mock-ref',
                'mock-commit',
                'mock-parent-commit',
                null,
                null,
                null,
                JobState::COMPLETED
            )
        );
    }
}
