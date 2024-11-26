<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Webhook\WebhookInterface;

interface WebhookProcessorServiceInterface
{
    public function process(WebhookInterface $webhook): void;
}
