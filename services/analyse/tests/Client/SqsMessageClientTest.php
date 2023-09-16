<?php

namespace App\Tests\Client;

use App\Client\SqsMessageClient;
use App\Enum\EnvironmentVariable;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use AsyncAws\Core\Response;
use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\Result\SendMessageResult;
use AsyncAws\Sqs\SqsClient;
use DateTimeImmutable;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Event\Upload;
use Packages\Models\Model\PublishableMessage\PublishablePullRequestMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SqsMessageClientTest extends TestCase
{
    public function testQueuePublishableMessage(): void
    {
        $publishableMessage = new PublishablePullRequestMessage(
            Upload::from(
                [
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'mock-owner',
                    'repository' => 'mock-repository',
                    'commit' => 'mock-commit',
                    'uploadId' => 'mock-uploadId',
                    'ref' => 'mock-ref',
                    'parent' => [],
                    'tag' => 'mock-tag',
                ]
            ),
            100.0,
            100.0,
            1,
            0,
            [],
            [],
            new DateTimeImmutable()
        );

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getInfo')
            ->willReturn(\Symfony\Component\HttpFoundation\Response::HTTP_OK);

        $mockSqsClient = $this->createMock(SqsClient::class);
        $mockSqsClient->expects($this->once())
            ->method('sendMessage')
            ->with(
                self::callback(
                    function (SendMessageRequest $messageRequest) use ($publishableMessage): true {
                        $this->assertEquals('publish-queue-url', $messageRequest->getQueueUrl());
                        $this->assertEquals('1a29f8adfb262a02370a33939d5f5840', $messageRequest->getMessageGroupId());
                        $this->assertEquals(json_encode($publishableMessage), $messageRequest->getMessageBody());

                        return true;
                    }
                )
            )
            ->willReturn(
                new SendMessageResult(
                    new Response(
                        $mockResponse,
                        $this->createMock(HttpClientInterface::class),
                        new NullLogger()
                    )
                )
            );

        $sqsMessageClient = new SqsMessageClient(
            $mockSqsClient,
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::PUBLISH_QUEUE->value => 'publish-queue-url',
                ]
            )
        );

        $successful = $sqsMessageClient->queuePublishableMessage($publishableMessage);

        $this->assertTrue($successful);
    }
}
