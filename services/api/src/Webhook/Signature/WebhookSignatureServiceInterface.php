<?php

namespace App\Webhook\Signature;

use App\Enum\WebhookType;
use App\Model\Webhook\SignedWebhookInterface;
use App\Model\Webhook\WebhookInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag('app.webhook_signature')]
interface WebhookSignatureServiceInterface
{
    /**
     * Get the payload type from a request.
     */
    public function getWebhookTypeFromRequest(Request $request): WebhookType;

    /**
     * Attempt to retrieve the payload signature from a request.
     */
    public function getPayloadSignatureFromRequest(Request $request): ?string;

    /**
     * Securely validate a signed webhook.
     */
    public function validatePayloadSignature(WebhookInterface&SignedWebhookInterface $webhook): bool;
}
