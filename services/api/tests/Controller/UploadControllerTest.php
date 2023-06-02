<?php

namespace Controller;

use App\Controller\UploadController;
use App\Exception\SigningException;
use App\Model\SignedUrl;
use App\Model\SigningParameters;
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
        $parameters = new SigningParameters($body['data']);

        $uploadService = $this->createMock(UploadService::class);
        $uploadService->expects($this->once())
            ->method('getSigningParametersFromRequest')
            ->willReturn($parameters);

        $uploadService->expects($this->once())
            ->method('buildSignedUploadUrl')
            ->with($parameters)
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

    public function testHandleUploadWithInvalidBody(): void
    {
        $uploadService = $this->createMock(UploadService::class);
        $uploadService->expects($this->once())
            ->method('getSigningParametersFromRequest')
            ->willThrowException(SigningException::invalidParameters());

        $uploadService->expects($this->never())
            ->method('buildSignedUploadUrl');

        $uploadController = new UploadController($uploadService, new NullLogger());

        $uploadController->setContainer($this->getContainer());

        $response = $uploadController->handleUpload($this->createMock(Request::class));

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(
            '{"error":"Parameters provided for signing do not match expectation."}',
            $response->getContent()
        );
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
                        'tag' => 'mock-tag',
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
                        'tag' => 'mock-tag',
                        'provider' => 'github',
                        'fileName' => 'test.xml'
                    ]
                ]
            ]
        ];
    }
}
