<?php

namespace App\Service;

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
        $signature = $request->headers->get('x-hub-signature-256');

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
            '/^(?<algorithm>(sha[0-9]+))\=(?<signature>(.*))$/i',
            $signature,
            $payloadSignature
        );

        if (!$payloadSignature['signature']) {
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
            'sha256',
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
