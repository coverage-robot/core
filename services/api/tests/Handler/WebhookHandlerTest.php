<?php

namespace App\Tests\Handler;

use App\Client\CognitoClientInterface;
use App\Handler\WebhookHandler;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Model\Webhook\WebhookInterface;
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
    public function testHandleSqsEnabledProject(WebhookInterface $webhook): void
    {
        $mockWebhookProcessor = $this->createMock(WebhookProcessorServiceInterface::class);
        $mockWebhookProcessor->expects($this->once())
            ->method('process')
            ->with($webhook);

        $mockCognitoClient = $this->createMock(CognitoClientInterface::class);
        $mockCognitoClient->expects($this->once())
            ->method('doesProjectExist')
            ->with(
                $webhook->getProvider(),
                $webhook->getOwner(),
                $webhook->getRepository(),
            )
            ->willReturn(true);

        $handler = new WebhookHandler(
            $mockWebhookProcessor,
            new NullLogger(),
            $mockCognitoClient,
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
    public function testHandleSqsWithNoProject(WebhookInterface $webhook): void
    {
        $mockWebhookProcessor = $this->createMock(WebhookProcessorServiceInterface::class);
        $mockWebhookProcessor->expects($this->never())
            ->method('process');

        $mockCognitoClient = $this->createMock(CognitoClientInterface::class);
        $mockCognitoClient->expects($this->once())
            ->method('doesProjectExist')
            ->with(
                $webhook->getProvider(),
                $webhook->getOwner(),
                $webhook->getRepository(),
            )
            ->willReturn(false);

        $handler = new WebhookHandler(
            $mockWebhookProcessor,
            new NullLogger(),
            $mockCognitoClient,
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
