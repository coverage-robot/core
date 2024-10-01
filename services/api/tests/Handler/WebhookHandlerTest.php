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
use Packages\Clients\Model\Object\Reference;
use Packages\Clients\Service\ObjectReferenceService;
use Packages\Event\Enum\JobState;
use Packages\Telemetry\Service\MetricServiceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
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
            ->method('doesProjectExist')
            ->with(
                $webhook->getProvider(),
                $webhook->getOwner(),
                $webhook->getRepository(),
            )
            ->willReturn(true);

        $mockSerializer = $this->createMock(SerializerInterface::class);
        $mockSerializer->expects($this->exactly(2))
            ->method('deserialize')
            ->willReturnCallback(
                function (string $data, string $type) use ($webhook): GithubCheckRunWebhook {
                    if ($type === Reference::class) {
                        throw new UnexpectedValueException('Unexpected value');
                    }

                    return $webhook;
                }
            );

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
            $this->createMock(ObjectReferenceService::class)
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


    public function testHandleSqsEnabledProjectWithObjectReference(): void
    {
        $reference = new Reference(
            'fake-path',
            '',
            new DateTimeImmutable('+1 year')
        );

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
            ->method('doesProjectExist')
            ->with(
                $webhook->getProvider(),
                $webhook->getOwner(),
                $webhook->getRepository(),
            )
            ->willReturn(true);

        $mockSerializer = $this->createMock(SerializerInterface::class);
        $mockSerializer->expects($this->exactly(2))
            ->method('deserialize')
            ->willReturnCallback(
                function (string $data, string $type) use ($webhook, $reference): Reference|GithubCheckRunWebhook {
                    if ($type === Reference::class) {
                        return $reference;
                    }

                    return $webhook;
                }
            );

        $objectReferenceService = $this->createMock(ObjectReferenceService::class);
        $objectReferenceService->expects($this->once())
            ->method('resolveReference')
            ->with($reference)
            ->willReturnCallback(
                function () {
                    $stream = fopen('php://temp', 'r+');
                    fwrite($stream, 'mock-payload');
                    rewind($stream);

                    return $stream;
                }
            );

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
            $objectReferenceService
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
        $reference = new Reference(
            'fake-path',
            '',
            new DateTimeImmutable('+1 year')
        );

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
            ->method('doesProjectExist')
            ->with(
                $webhook->getProvider(),
                $webhook->getOwner(),
                $webhook->getRepository(),
            )
            ->willReturn(false);

        $mockSerializer = $this->createMock(SerializerInterface::class);
        $mockSerializer->expects($this->exactly(2))
            ->method('deserialize')
            ->willReturnCallback(
                function (string $data, string $type) use ($webhook, $reference): Reference|GithubCheckRunWebhook {
                    if ($type === Reference::class) {
                        return $reference;
                    }

                    return $webhook;
                }
            );

        $objectReferenceService = $this->createMock(ObjectReferenceService::class);
        $objectReferenceService->expects($this->once())
            ->method('resolveReference')
            ->with($reference)
            ->willReturnCallback(
                function () {
                    $stream = fopen('php://temp', 'r+');
                    fwrite($stream, 'mock-payload');
                    rewind($stream);

                    return $stream;
                }
            );

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
            $objectReferenceService
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
