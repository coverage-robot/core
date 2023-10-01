<?php

namespace App\Tests\Service\Webhook;

use App\Client\EventBridgeEventClient;
use App\Entity\Job;
use App\Entity\Project;
use App\Enum\EnvironmentVariable;
use App\Enum\JobState;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Repository\JobRepository;
use App\Service\Webhook\JobStateChangeWebhookProcessor;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Packages\Models\Enum\Environment;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class JobStateChangeWebhookProcessorTest extends TestCase
{
    public function testProcessingWebhookCreatingNewJobForProject(): void
    {
        $mockProject = $this->createMock(Project::class);

        $mockJobRepository = $this->createMock(JobRepository::class);
        $jobRepositoryMatcher = $this->exactly(3);
        $mockJobRepository->expects($jobRepositoryMatcher)
            ->method('findOneBy')
            ->with(
                self::callback(
                    function (array $argument) use ($mockProject, $jobRepositoryMatcher) {
                        $this->assertEquals(
                            match ($jobRepositoryMatcher->numberOfInvocations()) {
                                1 => [
                                    'project' => $mockProject,
                                    'externalId' => '1',
                                    'commit' => 'mock-commit'
                                ],
                                2 => [
                                    'project' => $mockProject,
                                    'commit' => 'mock-commit'
                                ],
                                3 => [
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
        $mockEventBridgeEventClient->expects($this->exactly(2))
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

        $mockJobRepository = $this->createMock(JobRepository::class);
        $jobRepositoryMatcher = $this->exactly(3);
        $mockJobRepository->expects($jobRepositoryMatcher)
            ->method('findOneBy')
            ->with(
                self::callback(
                    function (array $argument) use ($mockProject, $jobRepositoryMatcher) {
                        $this->assertEquals(
                            match ($jobRepositoryMatcher->numberOfInvocations()) {
                                1 => [
                                    'project' => $mockProject,
                                    'externalId' => '1',
                                    'commit' => 'mock-commit'
                                ],
                                2 => [
                                    'project' => $mockProject,
                                    'commit' => 'mock-commit'
                                ],
                                3 => [
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
            )
        );
    }
}
