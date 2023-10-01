<?php

namespace App\Tests\Handler;

use App\Entity\Project;
use App\Enum\JobState;
use App\Handler\WebhookHandler;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Model\Webhook\WebhookInterface;
use App\Repository\ProjectRepository;
use App\Service\Webhook\WebhookProcessor;
use App\Tests\Mock\Factory\MockSerializerFactory;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WebhookHandlerTest extends KernelTestCase
{
    #[DataProvider('webhookDataProvider')]
    public function testHandleSqsDisabledProject(WebhookInterface $webhook): void
    {
        $mockProject = $this->createMock(Project::class);
        $mockProject->expects($this->atLeastOnce())
            ->method('isEnabled')
            ->willReturn(false);

        $mockWebhookProcessor = $this->createMock(WebhookProcessor::class);
        $mockWebhookProcessor->expects($this->never())
            ->method('process');

        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->with(
                [
                    'provider' => $webhook->getProvider(),
                    'repository' => $webhook->getRepository(),
                    'owner' => $webhook->getOwner(),
                ]
            )
            ->willReturn($mockProject);

        $handler = new WebhookHandler(
            $mockWebhookProcessor,
            new NullLogger(),
            $mockProjectRepository,
            MockSerializerFactory::getMock(
                $this,
                deserializeMap: [
                    [
                        'mock-payload',
                        WebhookInterface::class,
                        'json',
                        [],
                        $webhook
                    ]
                ]
            )
        );

        $sqsEvent = new SqsEvent(
            [
                'Records' => [
                    [
                        'eventSource' => 'aws:sqs',
                        'messageId' => '1',
                        'body' => 'mock-payload',
                        'attributes' => [
                            'ApproximateReceiveCount' => '1',
                            'SentTimestamp' => '1234',
                            'SequenceNumber' => '1',
                            'MessageGroupId' => '1',
                            'SenderId' => '987',
                            'MessageDeduplicationId' => '1',
                            'ApproximateFirstReceiveTimestamp' => '1234'
                        ]
                    ]
                ]
            ]
        );

        $handler->handleSqs(
            $sqsEvent,
            Context::fake()
        );
    }

    #[DataProvider('webhookDataProvider')]
    public function testHandleSqsEnabledProject(WebhookInterface $webhook): void
    {
        $mockProject = $this->createMock(Project::class);
        $mockProject->expects($this->atLeastOnce())
            ->method('isEnabled')
            ->willReturn(true);

        $mockWebhookProcessor = $this->createMock(WebhookProcessor::class);
        $mockWebhookProcessor->expects($this->once())
            ->method('process')
            ->with(
                $mockProject,
                $webhook
            );
        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->with(
                [
                    'provider' => $webhook->getProvider(),
                    'repository' => $webhook->getRepository(),
                    'owner' => $webhook->getOwner(),
                ]
            )
            ->willReturn($mockProject);

        $handler = new WebhookHandler(
            $mockWebhookProcessor,
            new NullLogger(),
            $mockProjectRepository,
            MockSerializerFactory::getMock(
                $this,
                deserializeMap: [
                    [
                        'mock-payload',
                        WebhookInterface::class,
                        'json',
                        [],
                        $webhook
                    ]
                ]
            )
        );

        $sqsEvent = new SqsEvent(
            [
                'Records' => [
                    [
                        'eventSource' => 'aws:sqs',
                        'messageId' => '1',
                        'body' => 'mock-payload',
                        'attributes' => [
                            'ApproximateReceiveCount' => '1',
                            'SentTimestamp' => '1234',
                            'SequenceNumber' => '1',
                            'MessageGroupId' => '1',
                            'SenderId' => '987',
                            'MessageDeduplicationId' => '1',
                            'ApproximateFirstReceiveTimestamp' => '1234'
                        ]
                    ]
                ]
            ]
        );

        $handler->handleSqs(
            $sqsEvent,
            Context::fake()
        );
    }

    public static function webhookDataProvider(): array
    {
        return [
            [
                new GithubCheckRunWebhook(
                    'mock-signature',
                    'mock-owner',
                    'mock-repository',
                    'mock-external-id',
                    'mock-app-id',
                    'mock-ref',
                    'mock-commit',
                    'mock-pull-request',
                    JobState::COMPLETED,
                    JobState::COMPLETED
                )
            ]
        ];
    }
}
