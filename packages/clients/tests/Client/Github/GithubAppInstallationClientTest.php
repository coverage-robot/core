<?php

namespace App\Tests\Client\Github;

use Github\Api\Apps;
use Github\AuthMethod;
use Github\HttpClient\Builder;
use Http\Client\Common\HttpMethodsClientInterface;
use Packages\Clients\Client\Github\GithubAppClient;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class GithubAppInstallationClientTest extends TestCase
{
    public function testClientAuthenticatesOnCreationWithOwner(): void
    {
        $mockAppsApi = $this->createMock(Apps::class);

        $mockAppsApi->expects($this->once())
            ->method('findInstallations')
            ->willReturn(
                [
                    [
                        'id' => 111,
                        'account' => [
                            'login' => 'mock-owner-2'
                        ]
                    ],
                    [
                        'id' => 222,
                        'account' => [
                            'login' => 'mock-owner'
                        ]
                    ]
                ]
            );

        $mockAppsApi->expects($this->once())
            ->method('createInstallationToken')
            ->with(222)
            ->willReturn([
                'token' => 'mock-token'
            ]);

        $appClient = $this->getMockBuilder(GithubAppClient::class)
            ->setConstructorArgs(
                [
                    'mock',
                    null,
                    $this->createMock(Builder::class),
                    'mock',
                    'https://mock-client.com'
                ]
            )
            ->onlyMethods(['getHttpClientBuilder'])
            ->getMock();

        $installationClient = $this->getMockBuilder(GithubAppInstallationClient::class)
            ->setConstructorArgs(
                [
                    $appClient
                ]
            )
            ->onlyMethods(['apps', 'authenticate'])
            ->getMock();

        $installationClient->expects($this->exactly(2))
            ->method('apps')
            ->willReturn($mockAppsApi);

        $installationClient->expects($this->once())
            ->method('authenticate')
            ->with('mock-token', null, AuthMethod::ACCESS_TOKEN);

        $installationClient->authenticateAsRepositoryOwner('mock-owner');
    }

    public function testConstructorDoesNotAuthenticateWithNoOwner(): void
    {
        $mockAppsApi = $this->createMock(Apps::class);

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getBody')
            ->willReturn($this->createMock(StreamInterface::class));

        $mockClient = $this->createMock(HttpMethodsClientInterface::class);
        $mockClient->method('get')
            ->willReturn($mockResponse);

        $mockBuilder = $this->createMock(Builder::class);
        $mockBuilder->method('getHttpClient')
            ->willReturn($mockClient);

        $mockGithubAppClient = $this->getMockBuilder(GithubAppClient::class)
            ->setConstructorArgs(
                [
                    'mock',
                    null,
                    $mockBuilder,
                    'mock',
                    'https://mock-client.com'
                ]
            )
            ->onlyMethods(['authenticate'])
            ->getMock();

        $mockAppsApi->expects($this->never())
            ->method('findInstallations');

        $mockAppsApi->expects($this->never())
            ->method('createInstallationToken');

        new GithubAppInstallationClient($mockGithubAppClient);
    }
}
