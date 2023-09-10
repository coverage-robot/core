<?php

namespace App\Service\Webhook;

use App\Model\Webhook\AbstractWebhook;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.webhook_processor')]
interface WebhookProcessorInterface
{
    public function process(AbstractWebhook $webhook): void;

    public function getProcessorEvent(): string;
}
