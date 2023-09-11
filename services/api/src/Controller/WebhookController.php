<?php

namespace App\Controller;

use App\Enum\EnvironmentVariable;
use App\Exception\AuthenticationException;
use App\Model\Webhook\AbstractWebhook;
use App\Repository\ProjectRepository;
use App\Service\EnvironmentService;
use App\Service\Webhook\WebhookProcessor;
use App\Service\WebhookSignatureService;
use InvalidArgumentException;
use Packages\Models\Enum\Provider;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

class WebhookController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $webhookLogger,
        private readonly WebhookSignatureService $webhookSignatureService,
        private readonly ProjectRepository $projectRepository,
        private readonly WebhookProcessor $webhookProcessor,
        private readonly EnvironmentService $environmentService,
    ) {
    }

    /**
     * @throws AuthenticationException
     */
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
            $webhook = AbstractWebhook::fromRequest(Provider::from($provider), $request);
        } catch (InvalidArgumentException $e) {
            // We're likely to receive this a lot from providers which send excess events
            // which we don't wish to process (i.e. pings from Github)
            $this->webhookLogger->info(
                'Invalid webhook payload received.',
                [
                    'exception' => $e,
                    'request' => $request->toArray()
                ]
            );
            return new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $project = $this->projectRepository
            ->findOneBy([
                'provider' => $provider,
                'repository' => $webhook->getRepository(),
                'owner' => $webhook->getOwner(),
            ]);

        if (!$project || !$project->isEnabled()) {
            $this->webhookLogger->warning(
                'Webhook received from disabled (or non-existent) project.',
                [
                    'provider' => $provider,
                    'repository' => $webhook->getRepository(),
                    'owner' => $webhook->getOwner(),
                    'project' => $project?->getId(),
                    'enabled' => $project?->isEnabled()
                ]
            );
            return new Response(null, Response::HTTP_UNAUTHORIZED);
        }

        $signature = $this->webhookSignatureService->getPayloadSignatureFromRequest($request);

        if (
            !$signature ||
            !$this->webhookSignatureService->validatePayloadSignature(
                $signature,
                $request->getContent(),
                $this->environmentService->getVariable(EnvironmentVariable::WEBHOOK_SECRET),
            )
        ) {
            $this->webhookLogger->warning(
                'Signature validation failed for webhook payload.',
                [
                    'provided' => $signature,
                    'computed' => $this->webhookSignatureService->computePayloadSignature(
                        $request->getContent(),
                        $this->environmentService->getVariable(EnvironmentVariable::WEBHOOK_SECRET),
                    ),
                    'payload' => $request->getContent()
                ]
            );
            return new Response(null, Response::HTTP_UNAUTHORIZED);
        }

        // TODO: It would be good to dispatch a message to a queue, rather than
        //  processing the message in the same request.
        $this->webhookProcessor->process($webhook);

        return new Response(null, Response::HTTP_OK);
    }
}
