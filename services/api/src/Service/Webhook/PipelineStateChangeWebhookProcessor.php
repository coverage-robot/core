<?php

namespace App\Service\Webhook;

use App\Enum\WebhookProcessorEvent;
use App\Model\Webhook\AbstractWebhook;
use Psr\Log\LoggerInterface;

class PipelineStateChangeWebhookProcessor implements WebhookProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface $webhookProcessorLogger
    ) {
    }

    public function process(AbstractWebhook $webhook): void
    {
        $this->webhookProcessorLogger->info(
            sprintf(
                'Processing pipeline state change (%s). State is: %s',
                (string)$webhook,
                $webhook->getState()->value
            )
        );
    }

    public static function getProcessorEvent(): string
    {
        return WebhookProcessorEvent::PIPELINE_STATE_CHANGE->value;
    }
}
