<?php

namespace App\Tests\Service\Webhook;

use App\Client\EventBridgeEventClient;
use App\Entity\Job;
use App\Entity\Project;
use App\Enum\JobState;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Repository\JobRepository;
use App\Repository\ProjectRepository;
use App\Service\Webhook\JobStateChangeWebhookProcessor;
use Packages\Models\Enum\Provider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class JobStateChangeWebhookProcessorTest extends TestCase
{
    public function testProcessingInvalidWebhookForProject(): void
    {
        $mockProjectRepository = $this->createMock(ProjectRepository::class);

        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->with(
                [
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository'
                ]
            )
            ->willReturn(null);

        $mockJobRepository = $this->createMock(JobRepository::class);
        $mockJobRepository->expects($this->never())
            ->method('findOneBy');

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventClient->expects($this->never())
            ->method('publishEvent');

        $jobStateChangeWebhookProcessor = new JobStateChangeWebhookProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $mockJobRepository,
            $mockEventBridgeEventClient
        );

        $jobStateChangeWebhookProcessor->process(
            new GithubCheckRunWebhook(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                '1',
                JobState::COMPLETED,
                JobState::COMPLETED,
                'mock-ref',
                'mock-commit',
                null
            )
        );
    }

    public function testProcessingWebhookCreatingNewJobForProject(): void
    {
        $mockProject = $this->createMock(Project::class);

        $mockProjectRepository = $this->createMock(ProjectRepository::class);

        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->with(
                [
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository'
                ]
            )
            ->willReturn($mockProject);

        $mockJobRepository = $this->createMock(JobRepository::class);
        $jobRepositoryMatcher = $this->exactly(2);
        $mockJobRepository->expects($jobRepositoryMatcher)
            ->method('findOneBy')
            ->with(
                self::callback(
                    function (array $argument) use ($mockProject, $jobRepositoryMatcher) {
                        $this->assertEquals(
                            match ($jobRepositoryMatcher->numberOfInvocations()) {
                                1 => [
                                    'project' => $mockProject,
                                    'externalId' => '1'
                                ],
                                2 => [
                                    'project' => $mockProject,
                                    'commit' => 'mock-commit',
                                    'state' => [
                                        JobState::IN_PROGRESS,
                                        JobState::PENDING,
                                        JobState::QUEUED,
                                    ]
                                ],
                            },
                            $argument
                        );

                        return true;
                    }
                )
            )
            ->willReturn(null);
        $mockJobRepository->expects($this->once())
            ->method('save');

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventClient->expects($this->once())
            ->method('publishEvent');

        $jobStateChangeWebhookProcessor = new JobStateChangeWebhookProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $mockJobRepository,
            $mockEventBridgeEventClient
        );

        $jobStateChangeWebhookProcessor->process(
            new GithubCheckRunWebhook(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                '1',
                JobState::COMPLETED,
                JobState::COMPLETED,
                'mock-ref',
                'mock-commit',
                null
            )
        );
    }

    public function testProcessingWebhookUpdatingExistingJobForProject(): void
    {
        $mockProject = $this->createMock(Project::class);

        $mockProjectRepository = $this->createMock(ProjectRepository::class);

        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->with(
                [
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository'
                ]
            )
            ->willReturn($mockProject);

        $mockJobRepository = $this->createMock(JobRepository::class);
        $jobRepositoryMatcher = $this->exactly(2);
        $mockJobRepository->expects($jobRepositoryMatcher)
            ->method('findOneBy')
            ->with(
                self::callback(
                    function (array $argument) use ($mockProject, $jobRepositoryMatcher) {
                        $this->assertEquals(
                            match ($jobRepositoryMatcher->numberOfInvocations()) {
                                1 => [
                                    'project' => $mockProject,
                                    'externalId' => '1'
                                ],
                                2 => [
                                    'project' => $mockProject,
                                    'commit' => 'mock-commit',
                                    'state' => [
                                        JobState::IN_PROGRESS,
                                        JobState::PENDING,
                                        JobState::QUEUED,
                                    ]
                                ],
                            },
                            $argument
                        );

                        return true;
                    }
                )
            )
            ->willReturn($this->createMock(Job::class));
        $mockJobRepository->expects($this->once())
            ->method('save');

        $mockEventBridgeEventClient = $this->createMock(EventBridgeEventClient::class);
        $mockEventBridgeEventClient->expects($this->never())
            ->method('publishEvent');

        $jobStateChangeWebhookProcessor = new JobStateChangeWebhookProcessor(
            new NullLogger(),
            $mockProjectRepository,
            $mockJobRepository,
            $mockEventBridgeEventClient
        );

        $jobStateChangeWebhookProcessor->process(
            new GithubCheckRunWebhook(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                '1',
                JobState::COMPLETED,
                JobState::COMPLETED,
                'mock-ref',
                'mock-commit',
                null
            )
        );
    }
}
