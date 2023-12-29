<?php

namespace Packages\Message\Tests\Client;

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
use Packages\Contracts\PublishableMessage\PublishableMessage;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Message\Client\PublishClient;
use Packages\Telemetry\Enum\EnvironmentVariable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

class PublishClientTest extends TestCase
{

    public function testPublishMessage(): void
    {
        $mockMessage = $this->createMock(PublishableMessageInterface::class);
        $mockMessage->method('getType')
            ->willReturn(PublishableMessage::PULL_REQUEST);
        $mockMessage->expects($this->once())
            ->method('getMessageGroup')
            ->willReturn('mock-message-group-value');

        $mockSqsClient = $this->createMock(SqsClient::class);

        $mockSerializer = $this->createMock(SerializerInterface::class);
        $mockSerializer->expects($this->once())
            ->method('serialize')
            ->with($mockMessage, 'json')
            ->willReturn('mock-serialized-json');

        $mockEnvironmentService = $this->createMock(EnvironmentServiceInterface::class);
        $mockEnvironmentService->expects($this->once())
            ->method('getEnvironment')
            ->willReturn(Environment::TESTING);
        $mockEnvironmentService->expects($this->exactly(2))
            ->method('getVariable')
            ->with(EnvironmentVariable::X_AMZN_TRACE_ID)
            ->willReturn('mock-trace-id');

        $publishClient = new PublishClient(
            $mockSqsClient,
            $mockEnvironmentService,
            $mockSerializer
        );


        $mockSqsClient->expects($this->once())
            ->method('getQueueUrl')
            ->with(
                self::callback(function (GetQueueUrlRequest $request) {
                    $this->assertEquals(
                        'coverage-publish-test.fifo',
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
            $publishClient->publishMessage(
                $mockMessage
            )
        );
    }

    public function testGetQueueUrl(): void
    {
        $mockSqsClient = $this->createMock(SqsClient::class);

        $publishClient = new PublishClient(
            $mockSqsClient,
            $this->createMock(EnvironmentServiceInterface::class),
            $this->createMock(SerializerInterface::class)
        );

        $mockSqsClient->expects($this->once())
            ->method('getQueueUrl')
            ->with(
                self::callback(function (GetQueueUrlRequest $request) {
                    $this->assertEquals(
                        'coverage-publish-test.fifo',
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

        $this->assertEquals(
            'mock-url',
            $publishClient->getQueueUrl('coverage-publish-test.fifo')
        );
    }
}
