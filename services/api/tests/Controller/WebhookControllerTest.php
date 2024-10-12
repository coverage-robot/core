<?php

namespace App\Tests\Controller;

use App\Client\WebhookQueueClientInterface;
use App\Enum\EnvironmentVariable;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Model\Webhook\Github\GithubPushWebhook;
use App\Model\Webhook\SignedWebhookInterface;
use App\Service\WebhookSignatureService;
use App\Webhook\Signature\Github\GithubWebhookSignatureService;
use Override;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Packages\Contracts\Environment\Service;

final class WebhookControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = WebhookControllerTest::createClient([
            /**
             * Turning off debug mode so that problem responses do not contain the full
             * stack trace.
             */
            'debug' => false
        ]);
    }

    public function testHandleWebhookEventWithInvalidEventHeader(): void
    {
        $mockWebhookQueueClient = $this->createMock(WebhookQueueClientInterface::class);
        $mockWebhookQueueClient->expects($this->never())
            ->method('dispatchWebhook');

        $this->getContainer()
            ->set(WebhookQueueClientInterface::class, $mockWebhookQueueClient);

        $this->client->request(
            method: Request::METHOD_POST,
            uri: '/event/' . Provider::GITHUB->value,
            server: ['HTTP_' . SignedWebhookInterface::GITHUB_EVENT_HEADER => 'some_other_invalid_event'],
            content: json_encode(['invalid' => 'body'])
        );

        $this->assertResponseIsUnprocessable();
        $this->assertEmpty($this->client->getResponse()->getContent());
    }

    public function testHandleWebhookEventWithInvalidBody(): void
    {
        $mockWebhookQueueClient = $this->createMock(WebhookQueueClientInterface::class);
        $mockWebhookQueueClient->expects($this->never())
            ->method('dispatchWebhook');

        $this->getContainer()
            ->set(WebhookQueueClientInterface::class, $mockWebhookQueueClient);

        $this->client->request(
            method: Request::METHOD_POST,
            uri: '/event/' . Provider::GITHUB->value,
            server: ['HTTP_' . SignedWebhookInterface::GITHUB_EVENT_HEADER => 'check_run'],
            content: json_encode(['invalid' => 'body'])
        );

        $this->assertResponseIsUnprocessable();
        $this->assertEmpty($this->client->getResponse()->getContent());
    }

    #[DataProvider('webhookPayloadDataProvider')]
    public function testHandleWebhookEventWithInvalidSignature(
        Provider $provider,
        string $type,
        string $webhookInstance,
        string $payload
    ): void {
        $mockWebhookQueueClient = $this->createMock(WebhookQueueClientInterface::class);
        $mockWebhookQueueClient->expects($this->never())
            ->method('dispatchWebhook');

        $this->getContainer()
            ->set(WebhookQueueClientInterface::class, $mockWebhookQueueClient);

        $this->client->request(
            method: Request::METHOD_POST,
            uri: '/event/' . $provider->value,
            server: [
                'HTTP_' . SignedWebhookInterface::GITHUB_EVENT_HEADER => $type,
                'HTTP_' . SignedWebhookInterface::GITHUB_SIGNATURE_HEADER => 'invalid-signature'
            ],
            content: $payload
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertEmpty($this->client->getResponse()->getContent());
    }

    #[DataProvider('webhookPayloadDataProvider')]
    public function testHandleWebhookEventWithValidBody(
        Provider $provider,
        string $type,
        string $webhookInstance,
        string $payload,
        string $signature
    ): void {
        $mockWebhookQueueClient = $this->createMock(WebhookQueueClientInterface::class);
        $mockWebhookQueueClient->expects($this->once())
            ->method('dispatchWebhook')
            ->with($this->isInstanceOf($webhookInstance));

        $this->getContainer()
            ->set(WebhookQueueClientInterface::class, $mockWebhookQueueClient);

        $this->getContainer()
            ->set(WebhookSignatureService::class, $this->getWebhookSignatureService());

        $this->client->request(
            method: Request::METHOD_POST,
            uri: '/event/' . $provider->value,
            server: [
                'HTTP_' . SignedWebhookInterface::GITHUB_EVENT_HEADER => $type,
                'HTTP_' . SignedWebhookInterface::GITHUB_SIGNATURE_HEADER => $signature
            ],
            content: $payload
        );

        $this->assertResponseIsSuccessful();
        $this->assertEmpty($this->client->getResponse()->getContent());
    }

    public static function webhookPayloadDataProvider(): iterable
    {
        foreach (glob(__DIR__ . '/../Fixture/Webhook/*.json') as $payload) {
            $requestBody = file_get_contents($payload);

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
                $requestBody,
                sprintf(
                    '%s=%s',
                    SignedWebhookInterface::SIGNATURE_ALGORITHM,
                    hash_hmac(
                        SignedWebhookInterface::SIGNATURE_ALGORITHM,
                        $requestBody,
                        'mock-webhook-secret'
                    )
                )
            ];
        }
    }

    private function getWebhookSignatureService(): WebhookSignatureService
    {
        return new WebhookSignatureService(
            [
                Provider::GITHUB->value => new GithubWebhookSignatureService(
                    new NullLogger(),
                    MockEnvironmentServiceFactory::createMock(
                        Environment::TESTING,
                        Service::API,
                        [
                            EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                        ]
                    )
                )
            ]
        );
    }
}
