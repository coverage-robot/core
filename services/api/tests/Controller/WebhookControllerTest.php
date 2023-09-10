<?php

namespace App\Tests\Controller;

use App\Controller\WebhookController;
use App\Entity\Project;
use App\Enum\EnvironmentVariable;
use App\Repository\ProjectRepository;
use App\Service\AuthTokenService;
use App\Service\Webhook\WebhookProcessor;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Packages\Models\Enum\Environment;
use Packages\Models\Enum\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookControllerTest extends KernelTestCase
{
    public function testHandleWebhookEventWithInvalidPayload(): void
    {
        $mockWebhookProcessor = $this->createMock(WebhookProcessor::class);
        $mockWebhookProcessor->expects($this->never())
            ->method('process');
        $mockAuthTokenService = $this->createMock(AuthTokenService::class);
        $mockAuthTokenService->expects($this->never())
            ->method('getPayloadSignatureFromRequest');
        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->never())
            ->method('findOneBy');

        $webhookController = new WebhookController(
            $mockAuthTokenService,
            $mockProjectRepository,
            $mockWebhookProcessor,
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            )
        );

        $webhookController->setContainer($this->getContainer());

        $request = new Request(content: json_encode(['invalid' => 'body']));

        $response = $webhookController->handleWebhookEvent(Provider::GITHUB->value, $request);

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    #[DataProvider('webhookPayloadDataProvider')]
    public function testHandleInvalidRepository(Provider $provider, string $payload): void
    {
        $mockWebhookProcessor = $this->createMock(WebhookProcessor::class);
        $mockWebhookProcessor->expects($this->never())
            ->method('process');
        $mockAuthTokenService = $this->createMock(AuthTokenService::class);
        $mockAuthTokenService->expects($this->never())
            ->method('getPayloadSignatureFromRequest');
        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'provider' => Provider::GITHUB->value,
                'repository' => 'mock-repository',
                'owner' => 'mock-owner',
            ])
            ->willReturn(null);


        $webhookController = new WebhookController(
            $mockAuthTokenService,
            $mockProjectRepository,
            $mockWebhookProcessor,
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            )
        );

        $webhookController->setContainer($this->getContainer());

        $request = new Request(content: $payload);

        $response = $webhookController->handleWebhookEvent($provider->value, $request);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    #[DataProvider('webhookPayloadDataProvider')]
    public function testHandleInvalidSignature(Provider $provider, string $payload): void
    {
        $mockWebhookProcessor = $this->createMock(WebhookProcessor::class);
        $mockWebhookProcessor->expects($this->never())
            ->method('process');
        $mockAuthTokenService = $this->createMock(AuthTokenService::class);
        $mockAuthTokenService->expects($this->once())
            ->method('getPayloadSignatureFromRequest')
            ->willReturn('mock-signature');
        $mockAuthTokenService->expects($this->once())
            ->method('validatePayloadSignature')
            ->with(
                'mock-signature',
                $payload,
                'mock-webhook-secret'
            )
            ->willReturn(false);

        $mockProject = $this->createMock(Project::class);
        $mockProject->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'provider' => Provider::GITHUB->value,
                'repository' => 'mock-repository',
                'owner' => 'mock-owner',
            ])
            ->willReturn($mockProject);

        $webhookController = new WebhookController(
            $mockAuthTokenService,
            $mockProjectRepository,
            $mockWebhookProcessor,
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            )
        );

        $webhookController->setContainer($this->getContainer());

        $request = new Request(content: $payload);

        $response = $webhookController->handleWebhookEvent($provider->value, $request);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    #[DataProvider('webhookPayloadDataProvider')]
    public function testSuccessfullySendsWebhookToProcess(Provider $provider, string $payload): void
    {
        $mockWebhookProcessor = $this->createMock(WebhookProcessor::class);
        $mockWebhookProcessor->expects($this->once())
            ->method('process');
        $mockAuthTokenService = $this->createMock(AuthTokenService::class);
        $mockAuthTokenService->expects($this->once())
            ->method('getPayloadSignatureFromRequest')
            ->willReturn('mock-signature');
        $mockAuthTokenService->expects($this->once())
            ->method('validatePayloadSignature')
            ->with(
                'mock-signature',
                $payload,
                'mock-webhook-secret'
            )
            ->willReturn(true);

        $mockProject = $this->createMock(Project::class);
        $mockProject->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $mockProjectRepository = $this->createMock(ProjectRepository::class);
        $mockProjectRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'provider' => Provider::GITHUB->value,
                'repository' => 'mock-repository',
                'owner' => 'mock-owner',
            ])
            ->willReturn($mockProject);

        $webhookController = new WebhookController(
            $mockAuthTokenService,
            $mockProjectRepository,
            $mockWebhookProcessor,
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            )
        );

        $webhookController->setContainer($this->getContainer());

        $request = new Request(content: $payload);

        $response = $webhookController->handleWebhookEvent($provider->value, $request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public static function webhookPayloadDataProvider(): iterable
    {
        foreach (glob(__DIR__ . '/../Fixture/Webhook/*.json') as $payload) {
            yield basename($payload) => [Provider::GITHUB, file_get_contents($payload)];
        }
    }
}
