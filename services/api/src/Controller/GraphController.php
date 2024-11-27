<?php

declare(strict_types=1);

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
use Symfony\Component\Routing\Requirement\EnumRequirement;

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
        [
            'default' => '/graph/{provider}/{owner}/{repository}/{ref}/badge.svg',

            /**
             * Legacy route which is maintained for backwards compatibility. When using this route
             * the ref is assumed to be `main`.
             *
             * This has been replaced by the new route which allows the ref to be specified
             * as a path parameter in the URL.
             *
             * For example: `graph/github/coverage-robot/core/some-ref/badge.svg`
             */
            'compat' => '/graph/{provider}/{owner}/{repository}/badge.svg',
        ],
        name: 'badge',
        requirements: [
            'provider' => new EnumRequirement(Provider::class),
            'owner' => '[A-Za-z0-9-_.]+',
            'repository' => '[A-Za-z0-9-_.]+',
            'ref' => '.+'
        ],
        defaults: ['_format' => 'json', 'ref' => 'main'],
        methods: ['GET']
    )]
    public function badge(
        Provider $provider,
        string $owner,
        string $repository,
        string $ref,
        Request $request
    ): Response {
        $parameters = new GraphParameters(
            $owner,
            $repository,
            $provider
        );

        $token = $this->authTokenService->getGraphTokenFromRequest($request);

        if ($token === null || !$this->authTokenService->getProjectUsingGraphToken($parameters, $token)) {
            throw AuthenticationException::invalidGraphToken();
        }

        $coveragePercentage = $this->dynamoDbClient->getCoveragePercentage(
            $parameters->getProvider(),
            $parameters->getOwner(),
            $parameters->getRepository(),
            $ref
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
