<?php

namespace App\Tests\Controller;

use App\Entity\Project;
use App\Model\GraphParameters;
use App\Repository\ProjectRepository;
use App\Service\AuthTokenServiceInterface;
use App\Service\BadgeServiceInterface;
use Packages\Contracts\Provider\Provider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class GraphControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    public function setUp(): void
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

    public function testBadgeWithValidParameters(): void
    {
        $mockAuthTokenService = $this->createMock(AuthTokenServiceInterface::class);
        $mockAuthTokenService->expects($this->once())
            ->method('getGraphTokenFromRequest')
            ->with($this->isInstanceOf(Request::class))
            ->willReturn('mock-graph-token');
        $mockAuthTokenService->expects($this->once())
            ->method('validateParametersWithGraphToken')
            ->with(
                self::callback(
                    static fn(GraphParameters $parameters): bool => $parameters->getOwner() === 'owner' &&
                        $parameters->getRepository() === 'repository' &&
                        $parameters->getProvider() === Provider::GITHUB
                ),
                'mock-graph-token'
            )
            ->willReturn(true);

        $this->getContainer()
            ->set(AuthTokenServiceInterface::class, $mockAuthTokenService);

        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'provider' => Provider::GITHUB->value,
                'owner' => 'owner',
                'repository' => 'repository',
            ])
            ->willReturn(new Project());

        $this->getContainer()
            ->set(ProjectRepository::class, $mockProjectRepository);

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

        $this->assertEquals(
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
            ->method('validateParametersWithGraphToken')
            ->with($this->isInstanceOf(GraphParameters::class), 'mock-graph-token')
            ->willReturn(false);

        $this->getContainer()
            ->set(AuthTokenServiceInterface::class, $mockAuthTokenService);

        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->never())
            ->method('findOneBy');

        $this->getContainer()
            ->set(ProjectRepository::class, $mockProjectRepository);

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
            ->method('validateParametersWithGraphToken');

        $this->getContainer()
            ->set(AuthTokenServiceInterface::class, $mockAuthTokenService);

        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->never())
            ->method('findOneBy');

        $this->getContainer()
            ->set(ProjectRepository::class, $mockProjectRepository);

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
