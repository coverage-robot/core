<?php

namespace App\Controller;

use App\Enum\EnvironmentVariable;
use App\Exception\AuthenticationException;
use App\Model\Webhook\AbstractWebhook;
use App\Repository\ProjectRepository;
use App\Service\AuthTokenService;
use App\Service\EnvironmentService;
use App\Service\Webhook\WebhookProcessor;
use InvalidArgumentException;
use Packages\Models\Enum\Provider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

class WebhookController extends AbstractController
{
    public function __construct(
        private readonly AuthTokenService $authTokenService,
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
            return new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $project = $this->projectRepository
            ->findOneBy([
                'provider' => $provider,
                'repository' => $webhook->getRepository(),
                'owner' => $webhook->getOwner(),
            ]);

        if (!$project || !$project->isEnabled()) {
            return new Response(null, Response::HTTP_UNAUTHORIZED);
        }

        $signature = $this->authTokenService->getPayloadSignatureFromRequest($request);

        if (
            !$signature ||
            !$this->authTokenService->validatePayloadSignature(
                $signature,
                $request->getContent(),
                $this->environmentService->getVariable(EnvironmentVariable::WEBHOOK_SECRET),
            )
        ) {
            return new Response(null, Response::HTTP_UNAUTHORIZED);
        }

        // TODO: It would be good to dispatch a message to a queue, rather than
        //  processing the message in the same request.
        $this->webhookProcessor->process($webhook);

        return new Response(null, Response::HTTP_OK);
    }
}
