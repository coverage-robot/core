<?php

namespace App\Controller;

use App\Entity\Project;
use App\Exception\AuthenticationException;
use App\Model\GraphParameters;
use App\Repository\ProjectRepository;
use App\Service\AuthTokenService;
use App\Service\AuthTokenServiceInterface;
use App\Service\BadgeService;
use App\Service\BadgeServiceInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Telemetry\Service\TraceContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class GraphController extends AbstractController
{
    public function __construct(
        private readonly BadgeServiceInterface $badgeService,
        private readonly AuthTokenServiceInterface $authTokenService,
        private readonly ProjectRepository $projectRepository
    ) {
        TraceContext::setTraceHeaderFromEnvironment();
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
            $parameters = new GraphParameters(
                $owner,
                $repository,
                Provider::from($provider)
            );

            $token = $this->authTokenService->getGraphTokenFromRequest($request);

            if ($token === null || !$this->authTokenService->validateParametersWithGraphToken($parameters, $token)) {
                throw AuthenticationException::invalidGraphToken();
            }

            /** @var Project $project */
            $project = $this->projectRepository->findOneBy([
                'provider' => $provider,
                'owner' => $owner,
                'repository' => $repository,
            ]);

            return new Response(
                $this->badgeService->renderCoveragePercentageBadge(
                    $project->getCoveragePercentage()
                ),
                Response::HTTP_OK,
                [
                    'Content-Type' => 'image/svg+xml',
                ]
            );
        } catch (AuthenticationException $authenticationException) {
            return $this->json(
                [
                    'error' => $authenticationException->getMessage()
                ],
                Response::HTTP_UNAUTHORIZED
            );
        }
    }
}
