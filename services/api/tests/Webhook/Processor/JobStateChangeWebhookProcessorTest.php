<?php

declare(strict_types=1);

namespace App\Tests\Webhook\Processor;

use App\Client\CognitoClientInterface;
use App\Model\Project;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Webhook\Processor\JobStateChangeWebhookProcessor;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Client\EventBusClientInterface;
use Packages\Event\Enum\JobState;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class JobStateChangeWebhookProcessorTest extends TestCase
{
    public function testProcessingWebhookCreatingNewJobForProject(): void
    {
        $mockEventBusClient = $this->createMock(EventBusClientInterface::class);
        $mockEventBusClient->expects($this->once())
            ->method('fireEvent');

        $mockCognitoClient = $this->createMock(CognitoClientInterface::class);
        $mockCognitoClient->expects($this->once())
            ->method('getProject')
            ->willReturn(new Project(
                provider: Provider::GITHUB,
                projectId: 'mock-project-id',
                owner: 'mock-owner',
                repository: 'mock-repository',
                email: 'mock-email',
                graphToken: 'mock-graph-token',
            ));

        $jobStateChangeWebhookProcessor = new JobStateChangeWebhookProcessor(
            new NullLogger(),
            $mockEventBusClient,
            $mockCognitoClient
        );

        $jobStateChangeWebhookProcessor->process(
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
        $mockEventBusClient = $this->createMock(EventBusClientInterface::class);
        $mockEventBusClient->expects($this->once())
            ->method('fireEvent');

        $mockCognitoClient = $this->createMock(CognitoClientInterface::class);
        $mockCognitoClient->expects($this->once())
            ->method('getProject')
            ->willReturn(new Project(
                provider: Provider::GITHUB,
                projectId: 'mock-project-id',
                owner: 'mock-owner',
                repository: 'mock-repository',
                email: 'mock-email',
                graphToken: 'mock-graph-token',
            ));

        $jobStateChangeWebhookProcessor = new JobStateChangeWebhookProcessor(
            new NullLogger(),
            $mockEventBusClient,
            $mockCognitoClient
        );

        $jobStateChangeWebhookProcessor->process(
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
}
