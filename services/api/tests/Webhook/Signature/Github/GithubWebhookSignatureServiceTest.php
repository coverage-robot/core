<?php

namespace App\Tests\Service;

use App\Enum\EnvironmentVariable;
use App\Model\Webhook\Github\GithubCheckRunWebhook;
use App\Model\Webhook\SignedWebhookInterface;
use App\Webhook\Signature\Github\GithubWebhookSignatureService;
use DateTimeImmutable;
use Packages\Configuration\Mock\MockEnvironmentServiceFactory;
use Packages\Contracts\Environment\Environment;
use Packages\Event\Enum\JobState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

final class GithubWebhookSignatureServiceTest extends TestCase
{
    public function testGetPayloadSignatureFromRequest(): void
    {
        $request = new Request();
        $request->headers->set(
            SignedWebhookInterface::GITHUB_SIGNATURE_HEADER,
            sprintf(
                '%s=mock-signature',
                SignedWebhookInterface::SIGNATURE_ALGORITHM
            )
        );

        $githubWebhookSignatureService = new GithubWebhookSignatureService(
            new NullLogger(),
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            )
        );

        $signature = $githubWebhookSignatureService->getPayloadSignatureFromRequest($request);

        $this->assertEquals('mock-signature', $signature);
    }

    public function testGetMissingPayloadSignatureFromRequest(): void
    {
        $request = new Request();

        $githubWebhookSignatureService = new GithubWebhookSignatureService(
            new NullLogger(),
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            )
        );

        $signature = $githubWebhookSignatureService->getPayloadSignatureFromRequest($request);

        $this->assertNull($signature);
    }

    #[DataProvider('invalidPayloadSignatureHeaderDataProvider')]
    public function testGetInvalidPayloadSignatureFromRequest(string $header): void
    {
        $request = new Request();
        $request->headers->set(SignedWebhookInterface::GITHUB_SIGNATURE_HEADER, $header);

        $githubWebhookSignatureService = new GithubWebhookSignatureService(
            new NullLogger(),
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => 'mock-webhook-secret'
                ]
            )
        );

        $signature = $githubWebhookSignatureService->getPayloadSignatureFromRequest($request);

        $this->assertNull($signature);
    }

    #[DataProvider('payloadAndSignatureDataProvider')]
    public function testValidatePayloadSignature(string $payload, string $secret, string $expectedSignature): void
    {
        $githubWebhookSignatureService = new GithubWebhookSignatureService(
            new NullLogger(),
            MockEnvironmentServiceFactory::createMock(
                Environment::TESTING,
                [
                    EnvironmentVariable::WEBHOOK_SECRET->value => $secret
                ]
            )
        );

        $isValid = $githubWebhookSignatureService->validatePayloadSignature(
            new GithubCheckRunWebhook(
                signature: $expectedSignature,
                owner: 'mock-owner',
                repository: 'mock-repository',
                externalId: 12,
                appId: 34,
                ref: 'mock-ref',
                commit: 'mock-commit',
                parent: null,
                pullRequest: 123,
                baseRef: null,
                baseCommit: null,
                jobState: JobState::COMPLETED,
                startedAt: new DateTimeImmutable(),
                completedAt: new DateTimeImmutable()
            ),
            new Request(content: $payload)
        );

        $this->assertTrue($isValid);
    }

    public static function payloadAndSignatureDataProvider(): iterable
    {
        yield 'Simple payload and signature' => [
            'mock-payload',
            'mock-secret',
            '0f1d8dff09f7a893f02bf38e54eddc4693d253bfc8d6ec337c6931fb7cff21c9'
        ];

        foreach (glob(__DIR__ . '/../Fixture/Webhook/*.json') as $file) {
            yield basename($file) => [
                file_get_contents($file),
                'mock-secret',
                hash_hmac(
                    SignedWebhookInterface::SIGNATURE_ALGORITHM,
                    file_get_contents($file),
                    'mock-secret'
                )
            ];
        }
    }

    public static function invalidPayloadSignatureHeaderDataProvider(): array
    {
        return [
            [
                sprintf('%s=', SignedWebhookInterface::SIGNATURE_ALGORITHM),
            ],
            [
                'mock-signature',
            ],
            [
                '',
            ],
        ];
    }
}
