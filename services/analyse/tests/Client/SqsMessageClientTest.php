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
use Packages\Models\Model\Tag;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SqsMessageClientTest extends KernelTestCase
{
    public function testQueuePublishableMessage(): void
    {
        $publishableMessage = new PublishablePullRequestMessage(
            new Upload(
                'mock-uploadId',
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-commit',
                [],
                'master',
                'project-root',
                12,
                new Tag('mock-tag', 'mock-commit'),
                new DateTimeImmutable('2023-09-02T10:12:00+00:00'),
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
                        $this->assertEquals('ba7fb642308245d6784bbc6bb7b28638', $messageRequest->getMessageGroupId());
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
            ),
            $this->getContainer()->get(SerializerInterface::class)
        );

        $successful = $sqsMessageClient->queuePublishableMessage($publishableMessage);

        $this->assertTrue($successful);
    }
}
