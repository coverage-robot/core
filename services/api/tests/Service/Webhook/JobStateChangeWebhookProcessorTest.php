<?php

namespace App\Tests\Service\Webhook;

use App\Client\EventBridgeEventClient;
use App\Entity\Job;
use App\Entity\Project;
use App\Enum\EnvironmentVariable;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Repository\JobRepository;
use App\Service\Webhook\JobStateChangeWebhookProcessor;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\JobState;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class JobStateChangeWebhookProcessorTest extends TestCase
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
            ->method('findBy')
            ->with(
                [
                    'project' => $mockProject,
                    'commit' => 'mock-commit'
                ]
            )
            ->willReturn([
                $newJob
            ]);
        $mockJobRepository->expects($this->once())
            ->method('create')
            ->willReturn($newJob);
        $mockJobRepository->expects($this->once())
            ->method('save');

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventClient->expects($this->once())
            ->method('publishEvent');

        $jobStateChangeWebhookProcessor = new JobStateChangeWebhookProcessor(
            new NullLogger(),
            $mockJobRepository,
            $mockEventBridgeEventClient,
            MockEnvironmentServiceFactory::getMock(
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
                null,
                JobState::COMPLETED,
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
        $mockJobRepository->expects($this->once())
            ->method('findBy')
            ->with(
                [
                    'project' => $mockProject,
                    'commit' => 'mock-commit'
                ]
            )
            ->willReturn([$job]);
        $mockJobRepository->expects($this->never())
            ->method('create');
        $mockJobRepository->expects($this->once())
            ->method('save');

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventClient->expects($this->once())
            ->method('publishEvent');

        $jobStateChangeWebhookProcessor = new JobStateChangeWebhookProcessor(
            new NullLogger(),
            $mockJobRepository,
            $mockEventBridgeEventClient,
            MockEnvironmentServiceFactory::getMock(
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
                null,
                JobState::COMPLETED,
                JobState::COMPLETED
            ),
            true
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

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventClient->expects($this->never())
            ->method('publishEvent');

        $jobStateChangeWebhookProcessor = new JobStateChangeWebhookProcessor(
            new NullLogger(),
            $mockJobRepository,
            $mockEventBridgeEventClient,
            MockEnvironmentServiceFactory::getMock(
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
                null,
                JobState::COMPLETED,
                JobState::COMPLETED
            ),
            true
        );
    }
}
