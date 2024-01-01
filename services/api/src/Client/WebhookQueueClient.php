<?php

namespace App\Client;

use App\Model\Webhook\WebhookInterface;
use App\Service\WebhookValidationService;
use AsyncAws\Sqs\SqsClient;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\PublishableMessage\InvalidMessageException;
use Packages\Message\Client\PublishClient;
use Packages\Message\Service\MessageValidationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class WebhookQueueClient extends PublishClient
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
        SqsClient $sqsClient,
        EnvironmentServiceInterface $environmentService,
        SerializerInterface $serializer,
        MessageValidationService $messageValidationService,
        LoggerInterface $publishClientLogger
    ) {
        parent::__construct(
            $sqsClient,
            $environmentService,
            $serializer,
            $messageValidationService,
            $publishClientLogger
        );
    }

    public function dispatchWebhook(WebhookInterface $webhook): bool
    {
        try {
            $this->webhookValidationService->validate($webhook);
        } catch (InvalidMessageException $invalidMessageException) {
            $this->publishClientLogger->error(
                sprintf(
                    'Unable to dispatch %s as it failed validation.',
                    (string)$webhook
                ),
                [
                    'exception' => $invalidMessageException,
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
