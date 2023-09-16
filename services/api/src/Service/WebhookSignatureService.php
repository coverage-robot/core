<?php

namespace App\Service;

use App\Model\Webhook\Github\AbstractGithubWebhook;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class WebhookSignatureService
{
    public function __construct(
        private readonly LoggerInterface $webhookSignatureLogger
    ) {
    }

    /**
     * Attempt to retrieve the payload signature from a request.
     */
    public function getPayloadSignatureFromRequest(Request $request): ?string
    {
        $signature = $request->headers->get(AbstractGithubWebhook::SIGNATURE_HEADER);

        if (!$signature) {
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
            !$payloadSignature['algorithm'] == AbstractGithubWebhook::SIGNATURE_ALGORITHM
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

    /**
     * Compute the _expected_ sha256 payload signature, using a shared secret.
     */
    public function computePayloadSignature(string $payload, string $secret): string
    {
        return hash_hmac(
            AbstractGithubWebhook::SIGNATURE_ALGORITHM,
            $payload,
            $secret
        );
    }

    /**
     * Securely validate a payload against a provided signature and shared secret.
     */
    public function validatePayloadSignature(string $signature, string $payload, string $secret): bool
    {
        return hash_equals(
            $this->computePayloadSignature($payload, $secret),
            $signature
        );
    }
}
