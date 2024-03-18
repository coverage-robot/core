<?php

namespace App\Tests\Controller;

use App\Exception\SigningException;
use App\Model\SignedUrl;
use App\Model\SigningParameters;
use App\Service\AuthTokenServiceInterface;
use App\Service\UploadServiceInterface;
use DateTimeImmutable;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UploadControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = UploadControllerTest::createClient([
            /**
             * Turning off debug mode so that problem responses do not contain the full
             * stack trace.
             */
            'debug' => false
        ]);
    }

    #[DataProvider('validPayloadDataProvider')]
    public function testHandleSuccessfulUpload(SigningParameters $parameters): void
    {
        $uploadService = $this->createMock(UploadServiceInterface::class);
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

        $this->getContainer()
            ->set(UploadServiceInterface::class, $uploadService);

        $authTokenService = $this->createMock(AuthTokenServiceInterface::class);
        $authTokenService->expects($this->once())
            ->method('getUploadTokenFromRequest')
            ->willReturn('mock-project-token');
        $authTokenService->expects($this->once())
            ->method('validateParametersWithUploadToken')
            ->with($parameters, 'mock-project-token')
            ->willReturn(true);

        $this->getContainer()
            ->set(AuthTokenServiceInterface::class, $authTokenService);

        $this->client->request(
            Request::METHOD_POST,
            '/upload',
            [],
            [],
            [],
            json_encode($parameters)
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('json');

        $this->assertJsonStringEqualsJsonString(
            <<<JSON
            {
                "expiration": "2023-05-10T10:10:10+00:00",
                "signedUrl": "mock-signed-url",
                "uploadId": "mock-upload-id"
            }
            JSON,
            $this->client->getResponse()->getContent()
        );
    }

    public function testHandleUploadWithInvalidBody(): void
    {
        $uploadService = $this->createMock(UploadServiceInterface::class);
        $uploadService->expects($this->once())
            ->method('getSigningParametersFromRequest')
            ->willThrowException(SigningException::invalidSignature());
        $uploadService->expects($this->never())
            ->method('buildSignedUploadUrl');

        $this->getContainer()
            ->set(UploadServiceInterface::class, $uploadService);

        $authTokenService = $this->createMock(AuthTokenServiceInterface::class);
        $authTokenService->expects($this->never())
            ->method('getUploadTokenFromRequest');
        $authTokenService->expects($this->never())
            ->method('validateParametersWithUploadToken');

        $this->getContainer()
            ->set(AuthTokenServiceInterface::class, $authTokenService);

        $this->client->request(
            Request::METHOD_POST,
            '/upload',
            [],
            [],
            [],
            'invalid-json'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->assertJsonStringEqualsJsonString(
            <<<JSON
            {
                "detail": "The signature provided is invalid.",
                "status": 400,
                "title": "Bad Request",
                "type": "http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html"
            }
            JSON,
            $this->client->getResponse()->getContent()
        );
    }

    #[DataProvider('validPayloadDataProvider')]
    public function testHandleUploadWithMissingToken(SigningParameters $parameters): void
    {
        $uploadService = $this->createMock(UploadServiceInterface::class);
        $uploadService->expects($this->once())
            ->method('getSigningParametersFromRequest')
            ->willReturn($parameters);
        $uploadService->expects($this->never())
            ->method('buildSignedUploadUrl');

        $this->getContainer()
            ->set(UploadServiceInterface::class, $uploadService);

        $authTokenService = $this->createMock(AuthTokenServiceInterface::class);
        $authTokenService->expects($this->once())
            ->method('getUploadTokenFromRequest')
            ->willReturn(null);
        $authTokenService->expects($this->never())
            ->method('validateParametersWithUploadToken');

        $this->getContainer()
            ->set(AuthTokenServiceInterface::class, $authTokenService);

        $this->client->request(
            Request::METHOD_POST,
            '/upload',
            [],
            [],
            [],
            'invalid-json'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->assertJsonStringEqualsJsonString(
            <<<JSON
            {
                "detail": "The provided upload token is invalid.",
                "status": 401,
                "title": "Unauthorized",
                "type": "http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html"
            }
            JSON,
            $this->client->getResponse()->getContent()
        );
    }

    #[DataProvider('validPayloadDataProvider')]
    public function testHandleUploadWithInvalidToken(SigningParameters $parameters): void
    {
        $uploadService = $this->createMock(UploadServiceInterface::class);
        $uploadService->expects($this->once())
            ->method('getSigningParametersFromRequest')
            ->willReturn($parameters);
        $uploadService->expects($this->never())
            ->method('buildSignedUploadUrl');

        $this->getContainer()
            ->set(UploadServiceInterface::class, $uploadService);

        $authTokenService = $this->createMock(AuthTokenServiceInterface::class);
        $authTokenService->expects($this->once())
            ->method('getUploadTokenFromRequest')
            ->willReturn('mock-token');
        $authTokenService->expects($this->once())
            ->method('validateParametersWithUploadToken')
            ->willReturn(false);

        $this->getContainer()
            ->set(AuthTokenServiceInterface::class, $authTokenService);

        $this->client->request(
            Request::METHOD_POST,
            '/upload',
            [],
            [],
            [],
            'invalid-json'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->assertJsonStringEqualsJsonString(
            <<<JSON
            {
                "detail": "The provided upload token is invalid.",
                "status": 401,
                "title": "Unauthorized",
                "type": "http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html"
            }
            JSON,
            $this->client->getResponse()->getContent()
        );
    }

    public static function validPayloadDataProvider(): array
    {
        return [
            'With pull request' => [
                new SigningParameters(
                    owner: 'mock-owner-id',
                    repository: 'mock-repository-name',
                    provider: Provider::GITHUB,
                    fileName: 'some/root/test.xml',
                    projectRoot: 'some/root/',
                    tag: 'mock-tag',
                    commit: 'mock-commit',
                    parent: ['mock-parent-hash'],
                    ref: 'mock-branch-reference',
                    pullRequest: '12',
                    baseRef: 'mock-base-ref',
                    baseCommit: 'mock-base-commit'
                )
            ],
            'Without to pull request' => [
                new SigningParameters(
                    owner: 'mock-owner-id',
                    repository: 'mock-repository-name',
                    provider: Provider::GITHUB,
                    fileName: 'some/root/test.xml',
                    projectRoot: 'some/root/',
                    tag: 'mock-tag',
                    commit: 'mock-commit',
                    parent: [],
                    ref: 'mock-branch-reference',
                    pullRequest: null,
                    baseRef: null,
                    baseCommit: null
                )
            ]
        ];
    }
}
