<?php

namespace App\Webhook\Signature\Github;

use App\Enum\EnvironmentVariable;
use App\Enum\WebhookType;
use App\Model\Webhook\SignedWebhookInterface;
use App\Model\Webhook\WebhookInterface;
use App\Webhook\Signature\WebhookSignatureServiceInterface;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Provider\ProviderAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class GithubWebhookSignatureService implements WebhookSignatureServiceInterface, ProviderAwareInterface
{
    public function __construct(
        private readonly LoggerInterface $webhookSignatureLogger,
        private readonly SerializerInterface&DenormalizerInterface $serializer,
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
    public function validatePayloadSignature(WebhookInterface&SignedWebhookInterface $webhook): bool
    {
        $payload = $this->serializer->serialize($webhook, 'json');
        $secret = $this->environmentService->getVariable(EnvironmentVariable::WEBHOOK_SECRET);

        return hash_equals(
            $this->computePayloadSignature($payload, $secret),
            $webhook->getSignature()
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
