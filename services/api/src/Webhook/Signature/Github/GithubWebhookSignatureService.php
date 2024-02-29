<?php

namespace App\Webhook\Signature\Github;

use App\Enum\EnvironmentVariable;
use App\Enum\WebhookType;
use App\Model\Webhook\SignedWebhookInterface;
use App\Model\Webhook\WebhookInterface;
use App\Webhook\Signature\ProviderWebhookSignatureServiceInterface;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Provider\Provider;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class GithubWebhookSignatureService implements ProviderWebhookSignatureServiceInterface
{
    public function __construct(
        private readonly LoggerInterface $webhookSignatureLogger,
        private readonly EnvironmentServiceInterface $environmentService
    ) {
    }

    #[Override]
    public function getWebhookTypeFromRequest(Request $request): WebhookType
    {
        $eventType = $request->headers->get(SignedWebhookInterface::GITHUB_EVENT_HEADER);

        return WebhookType::from(
            sprintf(
                '%s_%s',
                Provider::GITHUB->value,
                $eventType ?? ''
            )
        );
    }

    #[Override]
    public function getPayloadSignatureFromRequest(Request $request): ?string
    {
        $signature = $request->headers->get(SignedWebhookInterface::GITHUB_SIGNATURE_HEADER);

        if ($signature === null) {
            $this->webhookSignatureLogger->info(
                'Payload signature not provided in request.',
                [
                    'parameters' => $request->headers->all()
                ]
            );

            return null;
        }

        preg_match(
            '/^(?<algorithm>([a-z0-9]+))\=(?<signature>(.*))$/i',
            $signature,
            $payloadSignature
        );

        if (
            !isset($payloadSignature['signature'], $payloadSignature['algorithm']) ||
            !$payloadSignature['signature'] ||
            !$payloadSignature['algorithm'] == SignedWebhookInterface::SIGNATURE_ALGORITHM
        ) {
            $this->webhookSignatureLogger->info(
                'Payload signature provided in an unsupported format.',
                [
                    'signature' => $payloadSignature,
                    'parameters' => $request->headers->all()
                ]
            );

            return null;
        }

        $this->webhookSignatureLogger->info(
            'Payload signature decoded successfully.',
            [
                'signature' => $payloadSignature,
                'parameters' => $request->headers->all()
            ]
        );

        return $payloadSignature['signature'];
    }

    #[Override]
    public function validatePayloadSignature(
        WebhookInterface&SignedWebhookInterface $webhook,
        Request $request
    ): bool {
        $secret = $this->environmentService->getVariable(EnvironmentVariable::WEBHOOK_SECRET);

        $signature = $webhook->getSignature();

        if ($signature === null) {
            return false;
        }

        return hash_equals(
        /**
         * We're using the request payload, as opposed to the serialized webhook, because the webhook
         * isn't guaranteed to contain all of the same properties, in the same order. Therefore its unlikely
         * to produce the same hash.
         */
            $this->computePayloadSignature($request->getContent(), $secret),
            $signature
        );
    }

    #[Override]
    public static function getProvider(): string
    {
        return Provider::GITHUB->value;
    }

    /**
     * Compute the _expected_ sha256 payload signature, using a shared secret.
     */
    private function computePayloadSignature(string $payload, string $secret): string
    {
        return hash_hmac(
            SignedWebhookInterface::SIGNATURE_ALGORITHM,
            $payload,
            $secret
        );
    }
}
