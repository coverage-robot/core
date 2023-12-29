<?php

namespace Packages\Message\Client;

use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\Sqs\Enum\MessageSystemAttributeNameForSends;
use AsyncAws\Sqs\Input\GetQueueUrlRequest;
use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\SqsClient;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Message\PublishableMessage\PublishableMessageInterface;
use Packages\Telemetry\Enum\EnvironmentVariable;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class PublishClient
{
    /**
     * The SQS queue (FIFO) which is used to publish messages to version control providers.
     *
     * This is dynamic based on the environment the application is running in
     * (i.e. coverage-publish-prod, coverage-publish-dev, etc).
     */
    private const string PUBLISH_QUEUE_NAME = 'coverage-publish-%s';

    public function __construct(
        protected readonly SqsClient $sqsClient,
        protected readonly EnvironmentServiceInterface $environmentService,
        protected readonly SerializerInterface $serializer
    ) {
    }

    public function publishMessage(PublishableMessageInterface $publishableMessage): bool
    {
        $request = [
            'QueueUrl' => $this->getQueueUrl($this->getPublishQueueName()),
            'MessageBody' => $this->serializer->serialize($publishableMessage, 'json'),
            'MessageGroupId' => $publishableMessage->getMessageGroup(),
        ];

        return $this->publishToQueueWithTraceHeader($request);
    }

    /**
     * Publish an SQS message onto the queue, with the trace header if it exists.
     */
    protected function publishToQueueWithTraceHeader(array $request): bool
    {
        if ($this->environmentService->getVariable(EnvironmentVariable::X_AMZN_TRACE_ID) !== '') {
            /**
             * The trace header will be propagated to the next service in the chain if provided
             * from a previous request.
             *
             * This value is propagated into the environment in a number of methods. But in the
             * SQS context that's handled by a trait in the event processors.
             *
             * @see TraceContext
             */
            $request['MessageSystemAttributes'] = [
                MessageSystemAttributeNameForSends::AWSTRACE_HEADER => [
                    'StringValue' => $this->environmentService->getVariable(EnvironmentVariable::X_AMZN_TRACE_ID),
                    'DataType' => 'String',
                ],
            ];
        }

        $response = $this->sqsClient->sendMessage(new SendMessageRequest($request));

        try {
            $response->resolve();

            return $response->info()['status'] === Response::HTTP_OK;
        } catch (HttpException) {
            return false;
        }
    }

    /**
     * Get the full SQS Queue URL for a queue (using its name).
     */
    public function getQueueUrl(string $queueName): ?string
    {
        $response = $this->sqsClient->getQueueUrl(
            new GetQueueUrlRequest(
                [
                    'QueueName' => $queueName
                ]
            )
        );

        $queueUrl = $response->getQueueUrl();

        if (!$queueUrl) {
            throw new RuntimeException(
                sprintf(
                    'Could not get queue url for %s',
                    $queueName
                )
            );
        }

        return $queueUrl;
    }

    /**
     * Get the environment-specific queue name for the publish queue.
     */
    private function getPublishQueueName(): string
    {
        return sprintf(
            self::PUBLISH_QUEUE_NAME,
            $this->environmentService->getEnvironment()->value
        );
    }
}
