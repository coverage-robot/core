<?php

namespace App\Client;

use App\Model\Webhook\WebhookInterface;
use Packages\Message\Client\PublishClient;

class WebhookQueueClient extends PublishClient
{
    /**
     * The SQS queue (FIFO) which is used to process Webhooks from version control providers.
     *
     * This is dynamic based on the environment the application is running in
     * (i.e. coverage-webhooks-prod, coverage-webhooks-dev, etc).
     */
    private const string WEBHOOKS_QUEUE_NAME = 'coverage-webhooks-%s.fifo';

    public function publishWebhook(WebhookInterface $webhook): bool
    {
        $request = [
            'QueueUrl' => $this->getQueueUrl($this->getWebhooksQueueName()),
            'MessageBody' => $this->serializer->serialize($webhook, 'json'),
            'MessageGroupId' => $webhook->getMessageGroup(),
        ];

        return $this->publishToQueueWithTraceHeader($request);
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
