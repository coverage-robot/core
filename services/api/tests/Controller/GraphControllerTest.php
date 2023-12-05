<?php

namespace App\Tests\Controller;

use App\Controller\GraphController;
use App\Entity\Project;
use App\Model\GraphParameters;
use App\Repository\ProjectRepository;
use App\Service\AuthTokenService;
use App\Service\BadgeService;
use Packages\Contracts\Provider\Provider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GraphControllerTest extends KernelTestCase
{
    public function testBadgeWithValidParameters(): void
    {
        $mockBadgeService = $this->createMock(BadgeService::class);
        $mockAuthTokenService = $this->createMock(AuthTokenService::class);
        $mockProjectRepository = $this->createMock(ProjectRepository::class);

        $mockAuthTokenService->expects($this->once())
            ->method('getGraphTokenFromRequest')
            ->with($this->isInstanceOf(Request::class))
            ->willReturn('mock-graph-token');

        $mockAuthTokenService->expects($this->once())
            ->method('validateParametersWithGraphToken')
            ->with(
                self::callback(
                    static fn(GraphParameters $parameters) => $parameters->getOwner() === 'owner' &&
                        $parameters->getRepository() === 'repository' &&
                        $parameters->getProvider() === Provider::GITHUB
                ),
                'mock-graph-token'
            )
            ->willReturn(true);

        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'provider' => Provider::GITHUB->value,
                'owner' => 'owner',
                'repository' => 'repository',
            ])
            ->willReturn($this->createMock(Project::class));

        $mockBadgeService->expects($this->once())
            ->method('renderCoveragePercentageBadge')
            ->with(null)
            ->willReturn('<svg></svg>');

        $uploadController = new GraphController($mockBadgeService, $mockAuthTokenService, $mockProjectRepository);

        $uploadController->setContainer($this->getContainer());

        $response = $uploadController->badge(Provider::GITHUB->value, 'owner', 'repository', new Request());

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('<svg></svg>', $response->getContent());
    }

    public function testBadgeWithInvalidToken(): void
    {
        $mockBadgeService = $this->createMock(BadgeService::class);
        $mockAuthTokenService = $this->createMock(AuthTokenService::class);
        $mockProjectRepository = $this->createMock(ProjectRepository::class);

        $mockAuthTokenService->expects($this->once())
            ->method('getGraphTokenFromRequest')
            ->with($this->isInstanceOf(Request::class))
            ->willReturn('mock-graph-token');

        $mockAuthTokenService->expects($this->once())
            ->method('validateParametersWithGraphToken')
            ->with($this->isInstanceOf(GraphParameters::class), 'mock-graph-token')
            ->willReturn(false);

        $mockProjectRepository->expects($this->never())
            ->method('findOneBy');

        $mockBadgeService->expects($this->never())
            ->method('renderCoveragePercentageBadge');

        $uploadController = new GraphController($mockBadgeService, $mockAuthTokenService, $mockProjectRepository);

        $uploadController->setContainer($this->getContainer());

        $response = $uploadController->badge(Provider::GITHUB->value, 'owner', 'repository', new Request());

        $this->assertEquals(
            Response::HTTP_UNAUTHORIZED,
            $response->getStatusCode()
        );
    }

    public function testBadgeWithMissingToken(): void
    {
        $mockBadgeService = $this->createMock(BadgeService::class);
        $mockAuthTokenService = $this->createMock(AuthTokenService::class);
        $mockProjectRepository = $this->createMock(ProjectRepository::class);

        $mockAuthTokenService->expects($this->once())
            ->method('getGraphTokenFromRequest')
            ->with($this->isInstanceOf(Request::class))
            ->willReturn(null);

        $mockAuthTokenService->expects($this->never())
            ->method('validateParametersWithGraphToken');

        $mockProjectRepository->expects($this->never())
            ->method('findOneBy');

        $mockBadgeService->expects($this->never())
            ->method('renderCoveragePercentageBadge');

        $uploadController = new GraphController($mockBadgeService, $mockAuthTokenService, $mockProjectRepository);

        $uploadController->setContainer($this->getContainer());

        $response = $uploadController->badge(Provider::GITHUB->value, 'owner', 'repository', new Request());

        $this->assertEquals(
            Response::HTTP_UNAUTHORIZED,
            $response->getStatusCode()
        );
    }
}
