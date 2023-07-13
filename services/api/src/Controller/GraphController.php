<?php

namespace App\Controller;

use App\Exception\GraphException;
use App\Model\GraphParameters;
use App\Service\AuthTokenService;
use App\Service\BadgeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GraphController extends AbstractController
{
    public function __construct(
        private readonly BadgeService $badgeService,
        private readonly AuthTokenService $authTokenService
    ) {
    }

    /**
     * @throws GraphException
     */
    #[Route('/graph/{provider}/{owner}/{repository}', name: 'badge', methods: ['GET'])]
    public function badge(string $provider, string $owner, string $repository, Request $request): Response
    {
        $parameters = GraphParameters::from([
            'provider' => $provider,
            'owner' => $owner,
            'repository' => $repository,
        ]);

        $token = $this->authTokenService->getGraphTokenFromRequest($request);

        if (!$token || !$this->authTokenService->validateParametersWithGraphToken($parameters, $token)) {
            return new Response(
                null,
                Response::HTTP_UNAUTHORIZED
            );
        }

        return new Response(
            $this->badgeService->getBadge(),
            Response::HTTP_OK
        );
    }
}
