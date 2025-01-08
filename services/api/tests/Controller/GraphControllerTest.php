<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Client\DynamoDbClient;
use App\Client\DynamoDbClientInterface;
use App\Model\GraphParameters;
use App\Model\Project;
use App\Service\AuthTokenServiceInterface;
use App\Service\BadgeServiceInterface;
use Override;
use Packages\Contracts\Provider\Provider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class GraphControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = GraphControllerTest::createClient([
            /**
             * Turning off debug mode so that problem responses do not contain the full
             * stack trace.
             */
            'debug' => false
        ]);
    }

    public function testBackwardsCompatibleBadgeRouteWithValidParameters(): void
    {
        $mockAuthTokenService = $this->createMock(AuthTokenServiceInterface::class);
        $mockAuthTokenService->expects($this->once())
            ->method('getGraphTokenFromRequest')
            ->with($this->isInstanceOf(Request::class))
            ->willReturn('mock-graph-token');
        $mockAuthTokenService->expects($this->once())
            ->method('getProjectUsingGraphToken')
            ->with(
                self::callback(
                    static fn(GraphParameters $parameters): bool => $parameters->getOwner() === 'owner' &&
                        $parameters->getRepository() === 'repository' &&
                        $parameters->getProvider() === Provider::GITHUB
                ),
                'mock-graph-token'
            )
            ->willReturn(new Project(
                Provider::GITHUB,
                'mock-project-id',
                'owner',
                'repository',
                'mock-email',
                'mock-graph-token',
            ));

        $this->getContainer()->set(AuthTokenServiceInterface::class, $mockAuthTokenService);

        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getCoveragePercentage')
            ->with(Provider::GITHUB, 'owner', 'repository', 'main')
            ->willReturn(null);

        $this->getContainer()->set(DynamoDbClient::class, $mockDynamoDbClient);

        $mockBadgeService = $this->createMock(BadgeServiceInterface::class);
        $mockBadgeService->expects($this->once())
            ->method('renderCoveragePercentageBadge')
            ->with(null)
            ->willReturn('<svg></svg>');

        $this->getContainer()
            ->set(BadgeServiceInterface::class, $mockBadgeService);

        $this->client->request(
            Request::METHOD_GET,
            '/graph/' . Provider::GITHUB->value . '/owner/repository/badge.svg'
        );

        $this->assertResponseIsSuccessful();

        $this->assertSame(
            <<<HTML
            <svg></svg>
            HTML,
            $this->client->getResponse()->getContent()
        );
    }

    public function testCustomisableRefBadgeRouteWithValidParameters(): void
    {
        $mockAuthTokenService = $this->createMock(AuthTokenServiceInterface::class);
        $mockAuthTokenService->expects($this->once())
            ->method('getGraphTokenFromRequest')
            ->with($this->isInstanceOf(Request::class))
            ->willReturn('mock-graph-token');
        $mockAuthTokenService->expects($this->once())
            ->method('getProjectUsingGraphToken')
            ->with(
                self::callback(
                    static fn(GraphParameters $parameters): bool => $parameters->getOwner() === 'owner' &&
                        $parameters->getRepository() === 'repository' &&
                        $parameters->getProvider() === Provider::GITHUB
                ),
                'mock-graph-token'
            )
            ->willReturn(new Project(
                Provider::GITHUB,
                'mock-project-id',
                'owner',
                'repository',
                'mock-email',
                'mock-graph-token',
            ));

        $this->getContainer()->set(AuthTokenServiceInterface::class, $mockAuthTokenService);

        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
        $mockDynamoDbClient->expects($this->once())
            ->method('getCoveragePercentage')
            ->with(Provider::GITHUB, 'owner', 'repository', 'chore/custom-ref')
            ->willReturn(20.0);

        $this->getContainer()->set(DynamoDbClient::class, $mockDynamoDbClient);

        $mockBadgeService = $this->createMock(BadgeServiceInterface::class);
        $mockBadgeService->expects($this->once())
            ->method('renderCoveragePercentageBadge')
            ->with(20.0)
            ->willReturn('<svg></svg>');

        $this->getContainer()
            ->set(BadgeServiceInterface::class, $mockBadgeService);

        $this->client->request(
            Request::METHOD_GET,
            '/graph/' . Provider::GITHUB->value . '/owner/repository/chore/custom-ref/badge.svg'
        );

        $this->assertResponseIsSuccessful();

        $this->assertSame(
            <<<HTML
            <svg></svg>
            HTML,
            $this->client->getResponse()->getContent()
        );
    }

    public function testBadgeWithInvalidToken(): void
    {
        $mockAuthTokenService = $this->createMock(AuthTokenServiceInterface::class);
        $mockAuthTokenService->expects($this->once())
            ->method('getGraphTokenFromRequest')
            ->with($this->isInstanceOf(Request::class))
            ->willReturn('mock-graph-token');
        $mockAuthTokenService->expects($this->once())
            ->method('getProjectUsingGraphToken')
            ->with($this->isInstanceOf(GraphParameters::class), 'mock-graph-token')
            ->willReturn(false);

        $this->getContainer()->set(AuthTokenServiceInterface::class, $mockAuthTokenService);

        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
        $mockDynamoDbClient->expects($this->never())
            ->method('getCoveragePercentage');

        $this->getContainer()->set(DynamoDbClient::class, $mockDynamoDbClient);

        $mockBadgeService = $this->createMock(BadgeServiceInterface::class);
        $mockBadgeService->expects($this->never())
            ->method('renderCoveragePercentageBadge');

        $this->getContainer()
            ->set(BadgeServiceInterface::class, $mockBadgeService);

        $this->client->request(
            Request::METHOD_GET,
            '/graph/' . Provider::GITHUB->value . '/owner/repository/badge.svg'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->assertJsonStringEqualsJsonString(
            <<<JSON
            {
                "detail": "The provided graph token is invalid.",
                "status": 401,
                "title": "Unauthorized",
                "type": "http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html"
            }
            JSON,
            $this->client->getResponse()->getContent()
        );
    }

    public function testBadgeWithMissingToken(): void
    {
        $mockAuthTokenService = $this->createMock(AuthTokenServiceInterface::class);
        $mockAuthTokenService->expects($this->once())
            ->method('getGraphTokenFromRequest')
            ->with($this->isInstanceOf(Request::class))
            ->willReturn(null);
        $mockAuthTokenService->expects($this->never())
            ->method('getProjectUsingGraphToken');

        $this->getContainer()->set(AuthTokenServiceInterface::class, $mockAuthTokenService);

        $mockDynamoDbClient = $this->createMock(DynamoDbClientInterface::class);
        $mockDynamoDbClient->expects($this->never())
            ->method('getCoveragePercentage');

        $this->getContainer()->set(DynamoDbClient::class, $mockDynamoDbClient);

        $mockBadgeService = $this->createMock(BadgeServiceInterface::class);
        $mockBadgeService->expects($this->never())
            ->method('renderCoveragePercentageBadge');

        $this->getContainer()
            ->set(BadgeServiceInterface::class, $mockBadgeService);

        $this->client->request(
            Request::METHOD_GET,
            '/graph/' . Provider::GITHUB->value . '/owner/repository/badge.svg'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->assertJsonStringEqualsJsonString(
            <<<JSON
            {
                "detail": "The provided graph token is invalid.",
                "status": 401,
                "title": "Unauthorized",
                "type": "http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html"
            }
            JSON,
            $this->client->getResponse()->getContent()
        );
    }
}
