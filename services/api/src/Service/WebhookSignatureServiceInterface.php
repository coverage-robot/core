<?php

namespace App\Service;

use App\Enum\WebhookType;
use App\Model\Webhook\SignedWebhookInterface;
use App\Model\Webhook\WebhookInterface;
use Packages\Contracts\Provider\Provider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use ValueError;

interface WebhookSignatureServiceInterface
{
    /**
     * Get the type of webhook the request is for.
     *
     * @throws RuntimeException
     * @throws ValueError
     */
    public function getWebhookTypeFromRequest(Provider $provider, Request $request): WebhookType;

    /**
     * Get the signature for the payload received as a webhook.
     *
     *  @throws RuntimeException
     */
    public function getPayloadSignatureFromRequest(Provider $provider, Request $request): ?string;

    /**
     * Validate the signature of a webhook.
     *
     *  @throws RuntimeException
     */
    public function validatePayloadSignature(
        Provider $provider,
        WebhookInterface&SignedWebhookInterface $webhook,
        Request $request
    ): bool;
}
