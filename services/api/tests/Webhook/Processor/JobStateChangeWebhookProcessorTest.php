<?php

namespace App\Tests\Webhook;

use App\Client\EventBridgeEventClient;
use App\Entity\Job;
use App\Entity\Project;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Repository\JobRepository;
use App\Webhook\Processor\JobStateChangeWebhookProcessor;
use DateTimeImmutable;
use Packages\Event\Client\EventBusClientInterface;
use Packages\Event\Enum\JobState;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class JobStateChangeWebhookProcessorTest extends TestCase
{
    public function testProcessingWebhookCreatingNewJobForProject(): void
    {
        $mockProject = new Project();
        $newJob = new Job();

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
            $mockEventBusClient
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
                JobState::COMPLETED,
                new DateTimeImmutable(),
                new DateTimeImmutable()
            )
        );
    }

    public function testProcessingWebhookUpdatingExistingJobForProject(): void
    {
        $mockProject = new Project();
        $job = new Job();

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
            $mockEventBusClient
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
                JobState::COMPLETED,
                new DateTimeImmutable(),
                new DateTimeImmutable()
            )
        );
    }

    public function testProcessingWebhookForStateChangeTriggeredInternally(): void
    {
        $mockProject = new Project();

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
            $mockEventBusClient
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
                JobState::COMPLETED,
                new DateTimeImmutable(),
                new DateTimeImmutable()
            )
        );
    }
}
