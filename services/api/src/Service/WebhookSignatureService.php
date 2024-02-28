<?php

namespace App\Service;

use App\Enum\WebhookType;
use App\Model\Webhook\SignedWebhookInterface;
use App\Model\Webhook\WebhookInterface;
use App\Webhook\Signature\WebhookSignatureServiceInterface;
use Packages\Contracts\Provider\Provider;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\Request;

final class WebhookSignatureService
{
    /**
     * @param WebhookSignatureServiceInterface[] $webhookSignatureServices
     */
    public function __construct(
        #[TaggedIterator('app.webhook_signature', defaultIndexMethod: 'getProvider')]
        private readonly iterable $webhookSignatureServices
    ) {
    }

    /**
     * Get the type of webhook the request is for.
     *
     * @throws RuntimeException
     */
    public function getWebhookTypeFromRequest(Provider $provider, Request $request): WebhookType
    {
        return $this->getServiceForProvider($provider)
            ->getWebhookTypeFromRequest($request);
    }

    /**
     * Get the signature for the payload received as a webhook.
     *
     *  @throws RuntimeException
     */
    public function getPayloadSignatureFromRequest(Provider $provider, Request $request): ?string
    {
        return $this->getServiceForProvider($provider)
            ->getPayloadSignatureFromRequest($request);
    }

    /**
     * Validate the signature of a webhook.
     *
     *  @throws RuntimeException
     */
    public function validatePayloadSignature(Provider $provider, WebhookInterface&SignedWebhookInterface $webhook): bool
    {
        return $this->getServiceForProvider($provider)
            ->validatePayloadSignature($webhook);
    }

    /**
     * @throws RuntimeException
     */
    private function getServiceForProvider(Provider $provider): WebhookSignatureServiceInterface
    {
        $service = (iterator_to_array($this->webhookSignatureServices)[$provider->value]) ?? null;

        if (!$service instanceof WebhookSignatureServiceInterface) {
            throw new RuntimeException(
                sprintf(
                    'No webhook signature service for %s',
                    $provider->value
                )
            );
        }

        return $service;
    }
}
