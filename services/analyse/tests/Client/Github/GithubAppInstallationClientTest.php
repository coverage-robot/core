<?php

namespace App\Tests\Client\Github;

use App\Client\Github\GithubAppClient;
use App\Client\Github\GithubAppInstallationClient;
use Github\Api\Apps;
use Github\AuthMethod;
use Github\HttpClient\Builder;
use Http\Client\Common\HttpMethodsClientInterface;
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

        $installationClient = $this->getMockBuilder(GithubAppInstallationClient::class)
            ->disableOriginalConstructor()
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
