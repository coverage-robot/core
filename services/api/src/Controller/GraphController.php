<?php

namespace App\Controller;

use App\Entity\Project;
use App\Exception\AuthenticationException;
use App\Exception\GraphException;
use App\Model\GraphParameters;
use App\Repository\ProjectRepository;
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
        private readonly AuthTokenService $authTokenService,
        private readonly ProjectRepository $projectRepository
    ) {
    }

    #[Route(
        '/graph/{provider}/{owner}/{repository}/{type}',
        name: 'badge',
        requirements: ['type' => '(.+)\.svg'],
        methods: ['GET']
    )]
    public function badge(string $provider, string $owner, string $repository, Request $request): Response
    {
        try {
            $parameters = GraphParameters::from([
                'provider' => $provider,
                'owner' => $owner,
                'repository' => $repository,
            ]);

            $token = $this->authTokenService->getGraphTokenFromRequest($request);

            if (!$token || !$this->authTokenService->validateParametersWithGraphToken($parameters, $token)) {
                throw AuthenticationException::invalidGraphToken();
            }

            /** @var Project $project */
            $project = $this->projectRepository->findOneBy([
                'provider' => $provider,
                'owner' => $owner,
                'repository' => $repository,
            ]);

            return new Response(
                $this->badgeService->getBadge($project),
                Response::HTTP_OK,
                [
                    'Content-Type' => 'image/svg+xml',
                ]
            );
        } catch (AuthenticationException $e) {
            return $this->json(
                [
                    'error' => $e->getMessage()
                ],
                Response::HTTP_UNAUTHORIZED
            );
        } catch (GraphException $e) {
            return $this->json(
                [
                    'error' => $e->getMessage()
                ],
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
