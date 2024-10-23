<?php

declare(strict_types=1);

namespace App\Client;

use App\Model\Webhook\WebhookInterface;

interface WebhookQueueClientInterface
{
    public function dispatchWebhook(WebhookInterface $webhook): bool;
}
