<?php

namespace App\Controller;

use App\Client\SqsMessageClient;
use App\Enum\EnvironmentVariable;
use App\Enum\WebhookType;
use App\Model\Webhook\SignedWebhookInterface;
use App\Model\Webhook\WebhookInterface;
use App\Service\WebhookSignatureService;
use Exception;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Telemetry\TraceContext;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class WebhookController extends AbstractController
{
    /**
     * @param SerializerInterface&DenormalizerInterface&NormalizerInterface $serializer
     */
    public function __construct(
        private readonly LoggerInterface $webhookLogger,
        private readonly WebhookSignatureService $webhookSignatureService,
        private readonly SerializerInterface $serializer,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly SqsMessageClient $sqsMessageClient
    ) {
        TraceContext::setTraceHeaderFromEnvironment();
    }

    #[Route(
        '/event/{provider}',
        name: 'webhook_event',
        requirements: [
            'provider' => new EnumRequirement(
                Provider::class
            )
        ],
        methods: ['POST']
    )]
    public function handleWebhookEvent(string $provider, Request $request): Response
    {
        try {
            /**
             * Attach the provider and webhook type so that the serializer can
             * discriminate against the payload body uniformly and decode the
             * webhook event correctly.
             */
            $webhook = $this->serializer->denormalize(
                [
                    ...$request->toArray(),
                    'type' => WebhookType::tryFrom(
                        sprintf(
                            '%s_%s',
                            Provider::tryFrom($provider)?->value ?? '',
                            $request->headers->get(SignedWebhookInterface::GITHUB_EVENT_HEADER) ?? ''
                        )
                    )?->value,
                    'signature' => $this->webhookSignatureService->getPayloadSignatureFromRequest($request)
                ],
                WebhookInterface::class
            );
        } catch (Exception $exception) {
            // Occurs whenever the denormalized fails to denormalize the payload. This
            // common as providers frequently send payloads which aren't ones want to act on
            $this->webhookLogger->info(
                'Failed to denormalize webhook payload.',
                [
                    'exception' => $exception,
                ]
            );

            return new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (
            !$this->validateSignature(
                $webhook,
                $request->getContent()
            )
        ) {
            return new Response(null, Response::HTTP_UNAUTHORIZED);
        }

        $this->sqsMessageClient->queueIncomingWebhook($webhook);

        $this->webhookLogger->info(
            sprintf(
                '%s queued for handling successfully.',
                (string)$webhook
            ),
        );

        return new Response(null, Response::HTTP_OK);
    }


    /**
     * Validate the webhook against the signature provided by the provider.
     *
     * If the webhook is not signed, then we assume that the webhook is valid.
     */
    private function validateSignature(WebhookInterface $webhook, string $originalPayload): bool
    {
        if (!$webhook instanceof SignedWebhookInterface) {
            // The webhook isn't signed, so we assume that it's valid.
            return true;
        }

        $payload = $this->serializer->serialize($webhook, 'json');
        $secret = $this->environmentService->getVariable(
            EnvironmentVariable::WEBHOOK_SECRET
        );

        $signature = $webhook->getSignature();

        if (
            !$signature ||
            !$this->webhookSignatureService->validatePayloadSignature(
                $signature,
                $originalPayload,
                $secret
            )
        ) {
            $this->webhookLogger->warning(
                sprintf(
                    'Signature validation failed for webhook payload %s.',
                    (string)$webhook
                ),
                [
                    'provided' => $webhook->getSignature(),
                    'computed' => $this->webhookSignatureService->computePayloadSignature(
                        $payload,
                        $secret
                    )
                ]
            );

            return false;
        }

        return true;
    }
}
