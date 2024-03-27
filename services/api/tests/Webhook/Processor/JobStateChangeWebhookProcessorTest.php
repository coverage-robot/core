<?php

namespace App\Tests\Webhook\Processor;

use App\Model\Webhook\Github\GithubCheckRunWebhook;
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
        $mockEventBusClient = $this->createMock(EventBusClientInterface::class);
        $mockEventBusClient->expects($this->once())
            ->method('fireEvent');

        $jobStateChangeWebhookProcessor = new JobStateChangeWebhookProcessor(
            new NullLogger(),
            $mockEventBusClient
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

        $jobStateChangeWebhookProcessor = new JobStateChangeWebhookProcessor(
            new NullLogger(),
            $mockEventBusClient
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
