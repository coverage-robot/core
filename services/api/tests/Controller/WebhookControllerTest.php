<?php

namespace App\Tests\Controller;

use App\Client\SqsMessageClient;
use App\Controller\WebhookController;
use App\Enum\EnvironmentVariable;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Model\Webhook\SignedWebhookInterface;
use App\Service\WebhookSignatureService;
use App\Tests\Mock\Factory\MockEnvironmentServiceFactory;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Environment\Environment;
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

        $mockSqsMessageClient = $this->createMock(SqsMessageClient::class);
        $mockSqsMessageClient->expects($this->never())
            ->method('queueIncomingWebhook');

        $webhookController = new WebhookController(
            new NullLogger(),
            $mockWebhookSignatureService,
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            ),
            $mockSqsMessageClient
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

        $mockSqsMessageClient = $this->createMock(SqsMessageClient::class);
        $mockSqsMessageClient->expects($this->never())
            ->method('queueIncomingWebhook');

        $webhookController = new WebhookController(
            new NullLogger(),
            $mockWebhookSignatureService,
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            ),
            $mockSqsMessageClient
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

        $mockSqsMessageClient = $this->createMock(SqsMessageClient::class);
        $mockSqsMessageClient->expects($this->never())
            ->method('queueIncomingWebhook');

        $webhookController = new WebhookController(
            new NullLogger(),
            $mockWebhookSignatureService,
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            ),
            $mockSqsMessageClient
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

        $mockSqsMessageClient = $this->createMock(SqsMessageClient::class);
        $mockSqsMessageClient->expects($this->once())
            ->method('queueIncomingWebhook')
            ->with($this->isInstanceOf(GithubCheckRunWebhook::class));

        $webhookController = new WebhookController(
            new NullLogger(),
            $mockWebhookSignatureService,
            $this->getContainer()->get(SerializerInterface::class),
            MockEnvironmentServiceFactory::getMock(
                $this,
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            ),
            $mockSqsMessageClient
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
            yield basename($payload) => [Provider::GITHUB, 'check_run', file_get_contents($payload)];
        }
    }
}
