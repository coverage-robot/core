<?php

namespace App\Tests\Client;

use App\Client\SqsMessageClient;
use App\Enum\EnvironmentVariable;
use AsyncAws\Core\Response;
use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\Result\SendMessageResult;
use AsyncAws\Sqs\SqsClient;
use DateTimeImmutable;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\Upload;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
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
                uploadId: 'mock-uploadId',
                provider: Provider::GITHUB,
                owner: 'mock-owner',
                repository: 'mock-repository',
                commit: 'mock-commit',
                parent: [],
                ref: 'main',
                projectRoot: 'project-root',
                tag: new Tag('mock-tag', 'mock-commit'),
                pullRequest: 12,
                baseCommit: 'main',
                baseRef: 'commit-on-main',
                eventTime: new DateTimeImmutable('2023-09-02T10:12:00+00:00'),
            ),
            coveragePercentage: 100.0,
            diffCoveragePercentage: 100.0,
            successfulUploads: 1,
            tagCoverage: [],
            leastCoveredDiffFiles: [],
            baseCommit: 'commit-on-main',
            coverageChange: 0,
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
            MockEnvironmentServiceFactory::createMock(
                $this,
                Environment::TESTING,
                [
                    \Packages\Telemetry\Enum\EnvironmentVariable::X_AMZN_TRACE_ID->value => 'mock-trace-id',
                    EnvironmentVariable::PUBLISH_QUEUE->value => 'publish-queue-url',
                ]
            ),
            $this->getContainer()->get(SerializerInterface::class)
        );

        $successful = $sqsMessageClient->queuePublishableMessage($publishableMessage);

        $this->assertTrue($successful);
    }
}
