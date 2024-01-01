<?php

namespace App\Tests\Controller;

use App\Client\WebhookQueueClient;
use App\Controller\WebhookController;
use App\Enum\EnvironmentVariable;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Model\Webhook\Github\GithubPushWebhook;
use App\Model\Webhook\SignedWebhookInterface;
use App\Service\WebhookSignatureService;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class WebhookControllerTest extends KernelTestCase
{
    public function testHandleWebhookEventWithInvalidEventHeader(): void
    {
        $mockWebhookSignatureService = $this->createMock(WebhookSignatureService::class);
        $mockWebhookSignatureService->expects($this->once())
            ->method('getPayloadSignatureFromRequest');
        $mockWebhookSignatureService->expects($this->never())
            ->method('validatePayloadSignature');

        $mockWebhookQueueClient = $this->createMock(WebhookQueueClient::class);
        $mockWebhookQueueClient->expects($this->never())
            ->method('dispatchWebhook');

        $webhookController = new WebhookController(
            new NullLogger(),
            $mockWebhookSignatureService,
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::createMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            ),
            $mockWebhookQueueClient
        );

        $webhookController->setContainer($this->getContainer());

        $request = new Request(
            server: ['HTTP_' . SignedWebhookInterface::GITHUB_EVENT_HEADER => 'some_value'],
            content: json_encode(['invalid' => 'body'])
        );

        $response = $webhookController->handleWebhookEvent(Provider::GITHUB->value, $request);

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testHandleWebhookEventWithInvalidBody(): void
    {
        $mockWebhookSignatureService = $this->createMock(WebhookSignatureService::class);
        $mockWebhookSignatureService->expects($this->once())
            ->method('getPayloadSignatureFromRequest');
        $mockWebhookSignatureService->expects($this->never())
            ->method('validatePayloadSignature');

        $mockWebhookQueueClient = $this->createMock(WebhookQueueClient::class);
        $mockWebhookQueueClient->expects($this->never())
            ->method('dispatchWebhook');

        $webhookController = new WebhookController(
            new NullLogger(),
            $mockWebhookSignatureService,
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::createMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            ),
            $mockWebhookQueueClient
        );

        $webhookController->setContainer($this->getContainer());

        $request = new Request(
            server: ['HTTP_' . SignedWebhookInterface::GITHUB_EVENT_HEADER => 'check_run'],
            content: json_encode(['invalid' => 'body'])
        );

        $response = $webhookController->handleWebhookEvent(Provider::GITHUB->value, $request);

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    #[DataProvider('webhookPayloadDataProvider')]
    public function testHandleWebhookEventWithInvalidSignature(
        Provider $provider,
        string $type,
        string $webhookInstance,
        string $payload
    ): void {
        $mockWebhookSignatureService = $this->createMock(WebhookSignatureService::class);
        $mockWebhookSignatureService->expects($this->once())
            ->method('getPayloadSignatureFromRequest')
            ->willReturn('invalid-signature');
        $mockWebhookSignatureService->expects($this->once())
            ->method('validatePayloadSignature')
            ->with('invalid-signature', $payload, 'mock-webhook-secret')
            ->willReturn(false);

        $mockWebhookQueueClient = $this->createMock(WebhookQueueClient::class);
        $mockWebhookQueueClient->expects($this->never())
            ->method('dispatchWebhook');

        $webhookController = new WebhookController(
            new NullLogger(),
            $mockWebhookSignatureService,
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::createMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            ),
            $mockWebhookQueueClient
        );

        $webhookController->setContainer($this->getContainer());

        $request = new Request(
            server: ['HTTP_' . SignedWebhookInterface::GITHUB_EVENT_HEADER => $type],
            content: $payload
        );

        $response = $webhookController->handleWebhookEvent($provider->value, $request);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    #[DataProvider('webhookPayloadDataProvider')]
    public function testHandleWebhookEventWithValidBody(
        Provider $provider,
        string $type,
        string $webhookInstance,
        string $payload
    ): void {
        $mockWebhookSignatureService = $this->createMock(WebhookSignatureService::class);
        $mockWebhookSignatureService->expects($this->once())
            ->method('getPayloadSignatureFromRequest')
            ->willReturn('valid-signature');
        $mockWebhookSignatureService->expects($this->once())
            ->method('validatePayloadSignature')
            ->with('valid-signature', $payload, 'mock-webhook-secret')
            ->willReturn(true);

        $mockWebhookQueueClient = $this->createMock(WebhookQueueClient::class);
        $mockWebhookQueueClient->expects($this->once())
            ->method('dispatchWebhook')
            ->with($this->isInstanceOf($webhookInstance));

        $webhookController = new WebhookController(
            new NullLogger(),
            $mockWebhookSignatureService,
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::createMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            ),
            $mockWebhookQueueClient
        );

        $webhookController->setContainer($this->getContainer());

        $request = new Request(
            server: ['HTTP_' . SignedWebhookInterface::GITHUB_EVENT_HEADER => $type],
            content: $payload
        );

        $response = $webhookController->handleWebhookEvent($provider->value, $request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public static function webhookPayloadDataProvider(): iterable
    {
        foreach (glob(__DIR__ . '/../Fixture/Webhook/*.json') as $payload) {
            yield basename($payload) => [
                Provider::GITHUB,
                match (basename($payload)) {
                    'github_push.json' => 'push',
                    default => 'check_run'
                },
                match (basename($payload)) {
                    'github_push.json' => GithubPushWebhook::class,
                    default => GithubCheckRunWebhook::class
                },
                file_get_contents($payload)
            ];
        }
    }
}
