<?php

namespace App\Controller;

use App\Client\WebhookQueueClient;
use App\Client\WebhookQueueClientInterface;
use App\Model\Webhook\SignedWebhookInterface;
use App\Model\Webhook\WebhookInterface;
use App\Service\WebhookSignatureService;
use App\Service\WebhookSignatureServiceInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Telemetry\Service\TraceContext;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use ValueError;

final class WebhookController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $webhookLogger,
        #[Autowire(service: WebhookSignatureService::class)]
        private readonly WebhookSignatureServiceInterface $webhookSignatureService,
        private readonly SerializerInterface&DenormalizerInterface $serializer,
        #[Autowire(service: WebhookQueueClient::class)]
        private readonly WebhookQueueClientInterface $webhookQueueClient
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
            $provider = Provider::from($provider);

            $webhookType = $this->webhookSignatureService->getWebhookTypeFromRequest(
                $provider,
                $request
            );

            /**
             * Attach the provider and webhook type so that the serializer can
             * discriminate against the payload body uniformly and decode the
             * webhook event correctly.
             *
             * @var WebhookInterface $webhook
             */
            $webhook = $this->serializer->denormalize(
                array_merge(
                    $request->toArray(),
                    [
                        'type' => $webhookType->value,
                        'signature' => $this->webhookSignatureService->getPayloadSignatureFromRequest(
                            $provider,
                            $request
                        )
                    ]
                ),
                WebhookInterface::class
            );
        } catch (ExceptionInterface | ValueError $exception) {
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

        if (!$this->validateSignature($webhook, $request)) {
            return new Response(null, Response::HTTP_UNAUTHORIZED);
        }

        $this->webhookQueueClient->dispatchWebhook($webhook);

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
    private function validateSignature(WebhookInterface $webhook, Request $request): bool
    {
        if (!$webhook instanceof SignedWebhookInterface) {
            // The webhook isn't signed, so we assume that it's valid.
            return true;
        }

        if (!$this->webhookSignatureService->validatePayloadSignature($webhook->getProvider(), $webhook, $request)) {
            $this->webhookLogger->warning(
                sprintf(
                    'Signature validation failed for webhook payload %s.',
                    (string)$webhook
                ),
                [
                    'provided' => $webhook->getSignature()
                ]
            );

            return false;
        }

        return true;
    }
}
