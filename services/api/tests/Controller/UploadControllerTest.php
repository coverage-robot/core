<?php

namespace App\Tests\Controller;

use App\Controller\UploadController;
use App\Exception\SigningException;
use App\Model\SignedUrl;
use App\Model\SigningParameters;
use App\Service\AuthTokenService;
use App\Service\UploadService;
use DateTimeImmutable;
use Packages\Models\Enum\Provider;
use Packages\Telemetry\Metric\MetricService;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadControllerTest extends KernelTestCase
{
    #[DataProvider('validPayloadDataProvider')]
    public function testHandleSuccessfulUpload(SigningParameters $parameters): void
    {
        $uploadService = $this->createMock(UploadService::class);
        $uploadService->expects($this->once())
            ->method('getSigningParametersFromRequest')
            ->willReturn($parameters);

        $uploadService->expects($this->once())
            ->method('buildSignedUploadUrl')
            ->with($parameters)
            ->willReturn(
                new SignedUrl(
                    'mock-upload-id',
                    'mock-signed-url',
                    new DateTimeImmutable('2023-05-10 10:10:10')
                )
            );

        $authTokenService = $this->createMock(AuthTokenService::class);
        $authTokenService->expects($this->once())
            ->method('getUploadTokenFromRequest')
            ->willReturn('mock-project-token');
        $authTokenService->expects($this->once())
            ->method('validateParametersWithUploadToken')
            ->with($parameters, 'mock-project-token')
            ->willReturn(true);

        $uploadController = new UploadController(
            $uploadService,
            $authTokenService,
            new NullLogger(),
            $this->createMock(MetricService::class)
        );

        $uploadController->setContainer($this->getContainer());

        $response = $uploadController->handleUpload($this->createMock(Request::class));

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals(
            '{"uploadId":"mock-upload-id","signedUrl":"mock-signed-url","expiration":"2023-05-10T10:10:10+00:00"}',
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

        $authTokenService = $this->createMock(AuthTokenService::class);
        $authTokenService->expects($this->never())
            ->method('getUploadTokenFromRequest');
        $authTokenService->expects($this->never())
            ->method('validateParametersWithUploadToken');

        $uploadController = new UploadController(
            $uploadService,
            $authTokenService,
            new NullLogger(),
            $this->createMock(MetricService::class)
        );

        $uploadController->setContainer($this->getContainer());

        $response = $uploadController->handleUpload($this->createMock(Request::class));

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals(
            '{"error":"Parameters provided for signing do not match expectation."}',
            $response->getContent()
        );
    }

    #[DataProvider('validPayloadDataProvider')]
    public function testHandleUploadWithMissingToken(SigningParameters $parameters): void
    {
        $uploadService = $this->createMock(UploadService::class);
        $uploadService->expects($this->once())
            ->method('getSigningParametersFromRequest')
            ->willReturn($parameters);

        $uploadService->expects($this->never())
            ->method('buildSignedUploadUrl');

        $authTokenService = $this->createMock(AuthTokenService::class);
        $authTokenService->expects($this->once())
            ->method('getUploadTokenFromRequest')
            ->willReturn(null);
        $authTokenService->expects($this->never())
            ->method('validateParametersWithUploadToken');

        $uploadController = new UploadController(
            $uploadService,
            $authTokenService,
            new NullLogger(),
            $this->createMock(MetricService::class)
        );

        $uploadController->setContainer($this->getContainer());

        $response = $uploadController->handleUpload($this->createMock(Request::class));

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertEquals(
            '{"error":"The provided upload token is invalid."}',
            $response->getContent()
        );
    }

    #[DataProvider('validPayloadDataProvider')]
    public function testHandleUploadWithInvalidToken(SigningParameters $parameters): void
    {
        $uploadService = $this->createMock(UploadService::class);
        $uploadService->expects($this->once())
            ->method('getSigningParametersFromRequest')
            ->willReturn($parameters);

        $uploadService->expects($this->never())
            ->method('buildSignedUploadUrl');

        $authTokenService = $this->createMock(AuthTokenService::class);
        $authTokenService->expects($this->once())
            ->method('getUploadTokenFromRequest')
            ->willReturn('mock-token');
        $authTokenService->expects($this->once())
            ->method('validateParametersWithUploadToken')
            ->willReturn(false);

        $uploadController = new UploadController(
            $uploadService,
            $authTokenService,
            new NullLogger(),
            $this->createMock(MetricService::class)
        );

        $uploadController->setContainer($this->getContainer());

        $response = $uploadController->handleUpload($this->createMock(Request::class));

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertEquals(
            '{"error":"The provided upload token is invalid."}',
            $response->getContent()
        );
    }

    public static function validPayloadDataProvider(): array
    {
        return [
            'With pull request' => [
                new SigningParameters(
                    'mock-owner-id',
                    'mock-repository-name',
                    Provider::GITHUB,
                    'some/root/test.xml',
                    'some/root/',
                    'mock-tag',
                    'mock-commit',
                    ['mock-parent-hash'],
                    'mock-branch-reference',
                    '12'
                )
            ],
            'Without to pull request' => [
                new SigningParameters(
                    'mock-owner-id',
                    'mock-repository-name',
                    Provider::GITHUB,
                    'some/root/test.xml',
                    'some/root/',
                    'mock-tag',
                    'mock-commit',
                    [],
                    'mock-branch-reference',
                    null
                )
            ]
        ];
    }
}
