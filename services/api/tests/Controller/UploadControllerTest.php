<?php

namespace Controller;

use App\Controller\UploadController;
use App\Exception\SigningException;
use App\Model\SignedUrl;
use App\Service\UploadService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

class UploadControllerTest extends KernelTestCase
{
    #[DataProvider('validPayloadDataProvider')]
    public function testHandleUpload(array $body): void
    {
        $uploadService = $this->createMock(UploadService::class);
        $uploadService->expects($this->once())
            ->method('getSigningParametersFromRequest')
            ->willReturn($body['data']);

        $uploadService->expects($this->once())
            ->method('buildSignedUploadUrl')
            ->with(
                $body['data']['owner'],
                $body['data']['repository'],
                $body['data']['fileName'],
                $body['data']['pullRequest'] ?? null,
                $body['data']['commit'],
                $body['data']['parent'],
                $body['data']['provider']
            )
            ->willReturn(
                new SignedUrl(
                    'mock-signed-url',
                    new DateTimeImmutable('2023-05-10 10:10:10')
                )
            );

        $uploadController = new UploadController($uploadService, new NullLogger());

        $uploadController->setContainer($this->getContainer());

        $response = $uploadController->handleUpload($this->createMock(Request::class));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            '{"signedUrl":"mock-signed-url","expiration":"2023-05-10T10:10:10+00:00"}',
            $response->getContent()
        );
    }

    #[DataProvider('validPayloadDataProvider')]
    public function testHandleUploadWithInvalidBody(array $body): void
    {
        $uploadService = $this->createMock(UploadService::class);
        $uploadService->expects($this->once())
            ->method('getSigningParametersFromRequest')
            ->willThrowException(SigningException::invalidPayload(['mock']));

        $uploadService->expects($this->never())
            ->method('buildSignedUploadUrl');

        $uploadController = new UploadController($uploadService, new NullLogger());

        $uploadController->setContainer($this->getContainer());

        $response = $uploadController->handleUpload($this->createMock(Request::class));

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"error":"Invalid payload. Missing fields: mock."}', $response->getContent());
    }

    public static function validPayloadDataProvider(): array
    {
        return [
            'With pull request' => [
                 [
                    'data' => [
                        'owner' => '1',
                        'repository' => 'a',
                        'commit' => 2,
                        'pullRequest' => 12,
                        'parent' => 'd',
                        'provider' => 'github',
                        'fileName' => 'test.xml'
                    ]
                 ]
            ],
            'Without to pull request' => [
                [
                    'data' => [
                        'owner' => '1',
                        'repository' => 'a',
                        'commit' => 2,
                        'parent' => 'd',
                        'provider' => 'github',
                        'fileName' => 'test.xml'
                    ]
                ]
            ]
        ];
    }
}
