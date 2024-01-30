<?php

namespace App\Client;

use App\Model\Webhook\WebhookInterface;

interface WebhookQueueClientInterface
{
    public function dispatchWebhook(WebhookInterface $webhook): bool;
}
