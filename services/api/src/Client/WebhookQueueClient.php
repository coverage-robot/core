<?php

namespace App\Client;

use App\Exception\InvalidWebhookException;
use App\Model\Webhook\WebhookInterface;
use App\Service\WebhookValidationService;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\Sqs\Enum\MessageSystemAttributeNameForSends;
use AsyncAws\Sqs\Input\GetQueueUrlRequest;
use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\SqsClient;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Telemetry\Enum\EnvironmentVariable;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

final class WebhookQueueClient implements WebhookQueueClientInterface
{
    /**
     * The SQS queue (FIFO) which is used to process Webhooks from version control providers.
     *
     * This is dynamic based on the environment the application is running in
     * (i.e. coverage-webhooks-prod, coverage-webhooks-dev, etc).
     */
    private const string WEBHOOKS_QUEUE_NAME = 'coverage-webhooks-%s.fifo';

    public function __construct(
        private readonly WebhookValidationService $webhookValidationService,
        private readonly SqsClient $sqsClient,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $webhookQueueClientLogger
    ) {
    }

    public function dispatchWebhook(WebhookInterface $webhook): bool
    {
        try {
            $this->webhookValidationService->validate($webhook);
        } catch (InvalidWebhookException $invalidWebhookException) {
            $this->webhookQueueClientLogger->error(
                sprintf(
                    'Unable to dispatch %s as it failed validation.',
                    (string)$webhook
                ),
                [
                    'exception' => $invalidWebhookException,
                    'violations' => $invalidWebhookException->getViolations(),
                    'webhook' => $webhook
                ]
            );

            return false;
        }

        $request = [
            'QueueUrl' => $this->getQueueUrl($this->getWebhooksQueueName()),
            'MessageBody' => $this->serializer->serialize($webhook, 'json'),
            'MessageGroupId' => $webhook->getMessageGroup(),
        ];

        return $this->dispatchWithTraceHeader($request);
    }

    /**
     * Publish an SQS message onto the queue, with the trace header if it exists.
     *
     * @param array{QueueUrl: string, MessageBody: string, MessageGroupId: string} $request
     */
    private function dispatchWithTraceHeader(array $request): bool
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
    public function getQueueUrl(string $queueName): string
    {
        $response = $this->sqsClient->getQueueUrl(
            new GetQueueUrlRequest(
                [
                    'QueueName' => $queueName
                ]
            )
        );

        $queueUrl = $response->getQueueUrl();

        if ($queueUrl === null) {
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
     * Get the environment-specific queue name for the webhooks queue.
     */
    private function getWebhooksQueueName(): string
    {
        return sprintf(
            self::WEBHOOKS_QUEUE_NAME,
            $this->environmentService->getEnvironment()->value
        );
    }
}
