<?php

declare(strict_types=1);

namespace App\Tests\Handler;

use App\Client\CognitoClientInterface;
use App\Handler\WebhookHandler;
use App\Model\Project;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Service\WebhookProcessorServiceInterface;
use App\Service\WebhookValidationService;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use Packages\Event\Enum\JobState;
use Packages\Telemetry\Service\MetricServiceInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validation;

final class WebhookHandlerTest extends KernelTestCase
{
    public function testHandleSqsEnabledProjectWithDirectWebhookPayload(): void
    {
        $webhook = new GithubCheckRunWebhook(
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
        );

        $mockWebhookProcessor = $this->createMock(WebhookProcessorServiceInterface::class);
        $mockWebhookProcessor->expects($this->once())
            ->method('process')
            ->with($webhook);

        $mockCognitoClient = $this->createMock(CognitoClientInterface::class);
        $mockCognitoClient->expects($this->once())
            ->method('getProject')
            ->with(
                $webhook->getProvider(),
                $webhook->getOwner(),
                $webhook->getRepository(),
            )
            ->willReturn(
                new Project(
                    Provider::GITHUB,
                    'mock-project-id',
                    'owner',
                    'repository',
                    'mock-email',
                    'mock-graph-token',
                )
            );

        $mockSerializer = $this->createMock(SerializerInterface::class);
        $mockSerializer->expects($this->once())
            ->method('deserialize')
            ->willReturn($webhook);

        $handler = new WebhookHandler(
            $mockWebhookProcessor,
            new NullLogger(),
            $mockCognitoClient,
            $mockSerializer,
            new WebhookValidationService(
                Validation::createValidatorBuilder()
                    ->getValidator()
            ),
            $this->createMock(MetricServiceInterface::class),
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

    public function testHandleSqsWithNoProject(): void
    {
        $webhook = new GithubCheckRunWebhook(
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
        );

        $mockWebhookProcessor = $this->createMock(WebhookProcessorServiceInterface::class);
        $mockWebhookProcessor->expects($this->never())
            ->method('process');

        $mockCognitoClient = $this->createMock(CognitoClientInterface::class);
        $mockCognitoClient->expects($this->once())
            ->method('getProject')
            ->with(
                $webhook->getProvider(),
                $webhook->getOwner(),
                $webhook->getRepository(),
            )
            ->willReturn(null);

        $mockSerializer = $this->createMock(SerializerInterface::class);
        $mockSerializer->expects($this->once())
            ->method('deserialize')
            ->willReturn($webhook);

        $handler = new WebhookHandler(
            $mockWebhookProcessor,
            new NullLogger(),
            $mockCognitoClient,
            $mockSerializer,
            new WebhookValidationService(
                Validation::createValidatorBuilder()
                    ->getValidator()
            ),
            $this->createMock(MetricServiceInterface::class),
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
}
