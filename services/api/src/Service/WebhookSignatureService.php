<?php

namespace App\Service;

use App\Enum\WebhookType;
use App\Model\Webhook\SignedWebhookInterface;
use App\Model\Webhook\WebhookInterface;
use App\Webhook\Signature\ProviderWebhookSignatureServiceInterface;
use Override;
use Packages\Contracts\Provider\Provider;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

final class WebhookSignatureService implements WebhookSignatureServiceInterface
{
    /**
     * @param WebhookSignatureServiceInterface[] $webhookSignatureServices
     */
    public function __construct(
        #[AutowireIterator('app.webhook_signature', defaultIndexMethod: 'getProvider')]
        private readonly iterable $webhookSignatureServices
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getWebhookTypeFromRequest(Provider $provider, Request $request): WebhookType
    {
        return $this->getServiceForProvider($provider)
            ->getWebhookTypeFromRequest($request);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getPayloadSignatureFromRequest(Provider $provider, Request $request): ?string
    {
        return $this->getServiceForProvider($provider)
            ->getPayloadSignatureFromRequest($request);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function validatePayloadSignature(
        Provider $provider,
        WebhookInterface&SignedWebhookInterface $webhook,
        Request $request
    ): bool {
        return $this->getServiceForProvider($provider)
            ->validatePayloadSignature($webhook, $request);
    }

    /**
     * @throws RuntimeException
     */
    private function getServiceForProvider(Provider $provider): ProviderWebhookSignatureServiceInterface
    {
        $service = (iterator_to_array($this->webhookSignatureServices)[$provider->value]) ?? null;

        if (!$service instanceof ProviderWebhookSignatureServiceInterface) {
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
