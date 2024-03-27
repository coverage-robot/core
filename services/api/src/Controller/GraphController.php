<?php

namespace App\Controller;

use App\Client\DynamoDbClient;
use App\Client\DynamoDbClientInterface;
use App\Entity\Project;
use App\Exception\AuthenticationException;
use App\Model\GraphParameters;
use App\Repository\ProjectRepository;
use App\Service\AuthTokenServiceInterface;
use App\Service\BadgeServiceInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Telemetry\Service\TraceContext;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class GraphController extends AbstractController
{
    public function __construct(
        private readonly BadgeServiceInterface $badgeService,
        private readonly AuthTokenServiceInterface $authTokenService,
        private readonly ProjectRepository $projectRepository,
        #[Autowire(service: DynamoDbClient::class)]
        private readonly DynamoDbClientInterface $dynamoDbClient,
        private readonly LoggerInterface $graphLogger
    ) {
        TraceContext::setTraceHeaderFromEnvironment();
    }

    /**
     * @throws AuthenticationException
     */
    #[Route(
        '/graph/{provider}/{owner}/{repository}/{type}',
        name: 'badge',
        requirements: ['type' => '(.+)\.svg'],
        defaults: ['_format' => 'json'],
        methods: ['GET']
    )]
    public function badge(string $provider, string $owner, string $repository, Request $request): Response
    {
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

        $coveragePercentageFromRefMetadata = $this->dynamoDbClient->getCoveragePercentage(
            $parameters->getProvider(),
            $parameters->getOwner(),
            $parameters->getRepository(),
            // TODO(RM): Support different refs being passed through the URI.
            'main'
        );

        if ($coveragePercentageFromRefMetadata !== $project->getCoveragePercentage()) {
            $this->graphLogger->error(
                'Coverage percentage from ref metadata does not match project coverage percentage.',
                [
                    'projectTable' => $project->getCoveragePercentage(),
                    'refTable' => $coveragePercentageFromRefMetadata,
                ]
            );
        }

        return new Response(
            $this->badgeService->renderCoveragePercentageBadge(
                $project->getCoveragePercentage()
            ),
            Response::HTTP_OK,
            [
                'Content-Type' => 'image/svg+xml',
            ]
        );
    }
}
