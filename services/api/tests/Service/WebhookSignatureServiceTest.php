<?php

namespace App\Tests\Service;

use App\Model\Webhook\SignedWebhookInterface;
use App\Service\WebhookSignatureService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

class WebhookSignatureServiceTest extends TestCase
{
    public function testGetPayloadSignatureFromRequest(): void
    {
        $request = new Request();
        $request->headers->set(
            SignedWebhookInterface::SIGNATURE_HEADER,
            sprintf(
                '%s=mock-signature',
                SignedWebhookInterface::SIGNATURE_ALGORITHM
            )
        );

        $webhookSignatureService = new WebhookSignatureService(new NullLogger());

        $signature = $webhookSignatureService->getPayloadSignatureFromRequest($request);

        $this->assertEquals('mock-signature', $signature);
    }

    public function testGetMissingPayloadSignatureFromRequest(): void
    {
        $request = new Request();

        $webhookSignatureService = new WebhookSignatureService(new NullLogger());

        $signature = $webhookSignatureService->getPayloadSignatureFromRequest($request);

        $this->assertNull($signature);
    }

    #[DataProvider('invalidPayloadSignatureHeaderDataProvider')]
    public function testGetInvalidPayloadSignatureFromRequest(string $header): void
    {
        $request = new Request();
        $request->headers->set(SignedWebhookInterface::SIGNATURE_HEADER, $header);

        $webhookSignatureService = new WebhookSignatureService(new NullLogger());

        $signature = $webhookSignatureService->getPayloadSignatureFromRequest($request);

        $this->assertNull($signature);
    }

    #[DataProvider('payloadAndSignatureDataProvider')]
    public function testComputePayloadSignature(string $payload, string $secret, string $expectedSignature): void
    {
        $webhookSignatureService = new WebhookSignatureService(new NullLogger());

        $signature = $webhookSignatureService->computePayloadSignature(
            $payload,
            $secret
        );

        $this->assertEquals($expectedSignature, $signature);
    }

    #[DataProvider('payloadAndSignatureDataProvider')]
    public function testValidatePayloadSignature(string $payload, string $secret, string $expectedSignature): void
    {
        $webhookSignatureService = new WebhookSignatureService(new NullLogger());

        $isValid = $webhookSignatureService->validatePayloadSignature(
            $expectedSignature,
            $payload,
            $secret
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
