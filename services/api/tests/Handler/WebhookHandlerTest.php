<?php

namespace App\Tests\Handler;

use App\Entity\Project;
use App\Handler\WebhookHandler;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Model\Webhook\WebhookInterface;
use App\Repository\ProjectRepository;
use App\Service\WebhookProcessorServiceInterface;
use App\Service\WebhookValidationService;
use App\Tests\Mock\Factory\MockSerializerFactory;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use DateTimeImmutable;
use Packages\Event\Enum\JobState;
use Packages\Telemetry\Service\MetricServiceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validation;

final class WebhookHandlerTest extends KernelTestCase
{
    #[DataProvider('webhookDataProvider')]
    public function testHandleSqsDisabledProject(WebhookInterface $webhook): void
    {
        $mockProject = new Project();
        $mockProject->setEnabled(false);

        $mockWebhookProcessor = $this->createMock(WebhookProcessorServiceInterface::class);
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
            ),
            new WebhookValidationService(
                Validation::createValidatorBuilder()
                    ->getValidator()
            ),
            $this->createMock(MetricServiceInterface::class)
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
        $mockProject = new Project();
        $mockProject->setEnabled(true);

        $mockWebhookProcessor = $this->createMock(WebhookProcessorServiceInterface::class);
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
            ),
            new WebhookValidationService(
                Validation::createValidatorBuilder()
                    ->getValidator()
            ),
            $this->createMock(MetricServiceInterface::class)
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
                    'mock-parent-commit',
                    'mock-pull-request',
                    'mock-base-ref',
                    'mock-base-commit',
                    JobState::COMPLETED,
                    new DateTimeImmutable(),
                    new DateTimeImmutable()
                )
            ]
        ];
    }
}
