<?php

namespace App\Tests\Service\Webhook;

use App\Client\EventBridgeEventClient;
use App\Entity\Job;
use App\Entity\Project;
use App\Enum\JobState;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Repository\JobRepository;
use App\Service\Webhook\JobStateChangeWebhookProcessor;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class JobStateChangeWebhookProcessorTest extends TestCase
{
    public function testProcessingWebhookCreatingNewJobForProject(): void
    {
        $mockProject = $this->createMock(Project::class);

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
            $mockJobRepository,
            $mockEventBridgeEventClient
        );

        $jobStateChangeWebhookProcessor->process(
            $mockProject,
            new GithubCheckRunWebhook(
                '',
                'mock-owner',
                'mock-repository',
                '1',
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
            $mockJobRepository,
            $mockEventBridgeEventClient
        );

        $jobStateChangeWebhookProcessor->process(
            $mockProject,
            new GithubCheckRunWebhook(
                '',
                'mock-owner',
                'mock-repository',
                '1',
                'mock-ref',
                'mock-commit',
                null,
                JobState::COMPLETED,
                JobState::COMPLETED
            )
        );
    }
}
