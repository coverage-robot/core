<?php

namespace App\Tests\Client\Github;

use App\Client\Github\GithubAppClient;
use App\Client\Github\GithubAppInstallationClient;
use Github\Api\Apps;
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

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getBody')
            ->willReturn($this->createMock(StreamInterface::class));

        $mockClient = $this->createMock(HttpMethodsClientInterface::class);
        $mockClient->method("get")
            ->willReturn($mockResponse);

        $mockBuilder = $this->createMock(Builder::class);
        $mockBuilder->method("getHttpClient")
            ->willReturn($mockClient);

        $mockGithubAppClient = $this->getMockBuilder(GithubAppClient::class)
            ->setConstructorArgs(
                [
                    $mockBuilder,
                    'mock',
                    'https://mock-client.com'
                ]
            )
            ->onlyMethods(["authenticate", "api"])
            ->getMock();

        $mockAppsApi->expects($this->once())
            ->method("findInstallations")
            ->willReturn(
                [
                    [
                        'id' => 'mock-installation-id-2',
                        'account' => [
                            'login' => 'mock-owner-2'
                        ]
                    ],
                    [
                        'id' => 'mock-installation-id',
                        'account' => [
                            'login' => 'mock-owner'
                        ]
                    ]
                ]
            );

        $mockAppsApi->expects($this->once())
            ->method('createInstallationToken')
            ->with('mock-installation-id')
            ->willReturn([
                'token' => 'mock-token'
            ]);

        $mockGithubAppClient->expects($this->exactly(2))
            ->method("api")
            ->with("apps")
            ->willReturn($mockAppsApi);

        new GithubAppInstallationClient($mockGithubAppClient, "mock-owner");
    }

    public function testClientAuthenticatesDoesNotAuthenticateWithNoOwner(): void
    {
        $mockAppsApi = $this->createMock(Apps::class);

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getBody')
            ->willReturn($this->createMock(StreamInterface::class));

        $mockClient = $this->createMock(HttpMethodsClientInterface::class);
        $mockClient->method("get")
            ->willReturn($mockResponse);

        $mockBuilder = $this->createMock(Builder::class);
        $mockBuilder->method("getHttpClient")
            ->willReturn($mockClient);

        $mockGithubAppClient = $this->getMockBuilder(GithubAppClient::class)
            ->setConstructorArgs(
                [
                    $mockBuilder,
                    'mock',
                    'https://mock-client.com'
                ]
            )
            ->onlyMethods(["authenticate", "api"])
            ->getMock();

        $mockAppsApi->expects($this->never())
            ->method("findInstallations");

        $mockAppsApi->expects($this->never())
            ->method('createInstallationToken');

        $mockGithubAppClient->expects($this->never())
            ->method("api");

        new GithubAppInstallationClient($mockGithubAppClient);
    }
}
