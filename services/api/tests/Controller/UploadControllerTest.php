<?php

namespace Controller;

use App\Controller\UploadController;
use App\Exception\SigningException;
use App\Model\SignedUrl;
use App\Model\SigningParameters;
use App\Service\AuthTokenService;
use App\Service\UploadService;
use DateTimeImmutable;
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
            ->method('getProjectTokenFromRequest')
            ->willReturn('mock-project-token');
        $authTokenService->expects($this->once())
            ->method('validateParametersWithProjectToken')
            ->with($parameters, 'mock-project-token')
            ->willReturn(true);

        $uploadController = new UploadController($uploadService, $authTokenService, new NullLogger());

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
            ->method('getProjectTokenFromRequest');
        $authTokenService->expects($this->never())
            ->method('validateParametersWithProjectToken');

        $uploadController = new UploadController($uploadService, $authTokenService, new NullLogger());

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
            ->method('getProjectTokenFromRequest')
            ->willReturn(null);
        $authTokenService->expects($this->never())
            ->method('validateParametersWithProjectToken');

        $uploadController = new UploadController($uploadService, $authTokenService, new NullLogger());

        $uploadController->setContainer($this->getContainer());

        $response = $uploadController->handleUpload($this->createMock(Request::class));

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertEquals(
            '{"error":"The provided project token is invalid."}',
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
            ->method('getProjectTokenFromRequest')
            ->willReturn('mock-token');
        $authTokenService->expects($this->once())
            ->method('validateParametersWithProjectToken')
            ->willReturn(false);

        $uploadController = new UploadController($uploadService, $authTokenService, new NullLogger());

        $uploadController->setContainer($this->getContainer());

        $response = $uploadController->handleUpload($this->createMock(Request::class));

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertEquals(
            '{"error":"The provided project token is invalid."}',
            $response->getContent()
        );
    }

    public static function validPayloadDataProvider(): array
    {
        return [
            'With pull request' => [
                 SigningParameters::from([
                     'owner' => 'mock-owner-id',
                     'repository' => 'mock-repository-name',
                     'commit' => 1234,
                     'pullRequest' => 12,
                     'parent' => 'mock-parent-hash',
                     'ref' => 'mock-branch-reference',
                     'tag' => 'mock-tag',
                     'provider' => 'github',
                     'fileName' => 'some/root/test.xml',
                     'projectRoot' => 'some/root/'
                 ])
            ],
            'Without to pull request' => [
                SigningParameters::from([
                        'owner' => 'mock-owner-id',
                        'repository' => 'mock-repository-name',
                        'commit' => 2345,
                        'parent' => 'mock-parent-hash',
                        'ref' => 'mock-branch-reference',
                        'tag' => 'mock-tag',
                        'provider' => 'github',
                        'fileName' => 'some/root/test.xml',
                        'projectRoot' => 'some/root/'
                    ])
            ]
        ];
    }
}
