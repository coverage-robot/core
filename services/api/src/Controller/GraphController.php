<?php

namespace App\Controller;

use App\Client\DynamoDbClient;
use App\Client\DynamoDbClientInterface;
use App\Exception\AuthenticationException;
use App\Model\GraphParameters;
use App\Service\AuthTokenServiceInterface;
use App\Service\BadgeServiceInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Telemetry\Service\TraceContext;
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
        #[Autowire(service: DynamoDbClient::class)]
        private readonly DynamoDbClientInterface $dynamoDbClient
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

        $coveragePercentage = $this->dynamoDbClient->getCoveragePercentage(
            $parameters->getProvider(),
            $parameters->getOwner(),
            $parameters->getRepository(),
            // TODO(RM): Support different refs being passed through the URI.
            'main'
        );

        return new Response(
            $this->badgeService->renderCoveragePercentageBadge($coveragePercentage),
            Response::HTTP_OK,
            [
                'Content-Type' => 'image/svg+xml',
            ]
        );
    }
}
