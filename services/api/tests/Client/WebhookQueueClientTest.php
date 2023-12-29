<?php

namespace App\Tests\Client;

use App\Client\WebhookQueueClient;
use App\Enum\WebhookType;
use App\Model\Webhook\WebhookInterface;
use AsyncAws\Core\Response;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\Sqs\Enum\MessageSystemAttributeNameForSends;
use AsyncAws\Sqs\Input\GetQueueUrlRequest;
use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\Result\GetQueueUrlResult;
use AsyncAws\Sqs\Result\SendMessageResult;
use AsyncAws\Sqs\SqsClient;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Telemetry\Enum\EnvironmentVariable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

class WebhookQueueClientTest extends TestCase
{
    public function testPublishMessage(): void
    {
        $mockWebhook = $this->createMock(WebhookInterface::class);
        $mockWebhook->method('getType')
            ->willReturn(WebhookType::GITHUB_PUSH);
        $mockWebhook->expects($this->once())
            ->method('getMessageGroup')
            ->willReturn('mock-message-group-value');

        $mockSqsClient = $this->createMock(SqsClient::class);

        $mockSerializer = $this->createMock(SerializerInterface::class);
        $mockSerializer->expects($this->once())
            ->method('serialize')
            ->with($mockWebhook, 'json')
            ->willReturn('mock-serialized-json');

        $mockEnvironmentService = $this->createMock(EnvironmentServiceInterface::class);
        $mockEnvironmentService->expects($this->once())
            ->method('getEnvironment')
            ->willReturn(Environment::TESTING);
        $mockEnvironmentService->expects($this->exactly(2))
            ->method('getVariable')
            ->with(EnvironmentVariable::X_AMZN_TRACE_ID)
            ->willReturn('mock-trace-id');

        $webhookQueueClient = new WebhookQueueClient(
            $mockSqsClient,
            $mockEnvironmentService,
            $mockSerializer
        );

        $mockSqsClient->expects($this->once())
            ->method('getQueueUrl')
            ->with(
                self::callback(function (GetQueueUrlRequest $request) {
                    $this->assertEquals(
                        'coverage-webhooks-test',
                        $request->getQueueName()
                    );

                    return true;
                })
            )
            ->willReturn(
                ResultMockFactory::create(
                    GetQueueUrlResult::class,
                    [
                        'QueueUrl' => 'mock-url',
                    ]
                )
            );

        $result = new SendMessageResult(
            new Response(
                new MockResponse('', ['status' => \Symfony\Component\HttpFoundation\Response::HTTP_OK]),
                new MockHttpClient(),
                new NullLogger()
            )
        );

        $mockSqsClient->expects($this->once())
            ->method('sendMessage')
            ->with(
                self::callback(function (SendMessageRequest $request) {
                    $this->assertEquals(
                        'mock-url',
                        $request->getQueueUrl()
                    );
                    $this->assertEquals(
                        'mock-message-group-value',
                        $request->getMessageGroupId()
                    );
                    $this->assertEquals(
                        'mock-trace-id',
                        $request->getMessageSystemAttributes()[MessageSystemAttributeNameForSends::AWSTRACE_HEADER]
                            ->getStringValue()
                    );
                    $this->assertEquals(
                        'mock-serialized-json',
                        $request->getMessageBody()
                    );

                    return true;
                })
            )
            ->willReturn($result);

        $this->assertTrue(
            $webhookQueueClient->publishWebhook(
                $mockWebhook
            )
        );
    }
}
