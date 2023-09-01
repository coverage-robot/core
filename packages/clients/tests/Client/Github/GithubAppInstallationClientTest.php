<?php

namespace Packages\Clients\Tests\Client\Github;

use Github\Api\Apps;
use Github\AuthMethod;
use OutOfBoundsException;
use Packages\Clients\Client\Github\GithubAppClient;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use PHPUnit\Framework\TestCase;

class GithubAppInstallationClientTest extends TestCase
{
    public function testAuthenticatingAsInstallation(): void
    {
        $mockAppsWrapper = $this->createMock(Apps::class);
        $mockAppsWrapper->expects($this->once())
            ->method('createInstallationToken')
            ->willReturn(['token' => 'test-token']);

        $mockAppsWrapper->expects($this->once())
            ->method('findInstallations')
            ->willReturn([
                ['id' => 1, 'account' => ['login' => 'test-owner']],
                ['id' => 2, 'account' => ['login' => 'second-owner']]
            ]);

        $appClient = $this->createMock(GithubAppClient::class);
        $installationClient = $this->createMock(GithubAppClient::class);

        $installationClient->expects($this->once())
            ->method('authenticate')
            ->with(
                'test-token',
                null,
                AuthMethod::ACCESS_TOKEN
            );

        $appClient->method('apps')
            ->willReturn($mockAppsWrapper);

        $installation = new GithubAppInstallationClient(
            $appClient,
            $installationClient
        );

        $installation->authenticateAsRepositoryOwner('test-owner');
    }

    public function testAuthenticatingRepeatedlyAsSameInstallation(): void
    {
        $mockAppsWrapper = $this->createMock(Apps::class);
        $mockAppsWrapper->expects($this->once())
            ->method('createInstallationToken')
            ->willReturn(['token' => 'test-token']);

        $mockAppsWrapper->expects($this->once())
            ->method('findInstallations')
            ->willReturn([
                ['id' => 1, 'account' => ['login' => 'test-owner']]
            ]);

        $appClient = $this->createMock(GithubAppClient::class);
        $installationClient = $this->createMock(GithubAppClient::class);

        $installationClient->expects($this->once())
            ->method('authenticate')
            ->with(
                'test-token',
                null,
                AuthMethod::ACCESS_TOKEN
            );

        $appClient->method('apps')
            ->willReturn($mockAppsWrapper);

        $installation = new GithubAppInstallationClient(
            $appClient,
            $installationClient
        );

        $installation->authenticateAsRepositoryOwner('test-owner');

        // This should not trigger a second authentication
        $installation->authenticateAsRepositoryOwner('test-owner');
    }

    public function testAuthenticatingHandlesMissingInstallation(): void
    {
        $mockAppsWrapper = $this->createMock(Apps::class);
        $mockAppsWrapper->expects($this->never())
            ->method('createInstallationToken');

        $mockAppsWrapper->expects($this->once())
            ->method('findInstallations')
            ->willReturn([
                ['id' => 2, 'account' => ['login' => 'second-owner']]
            ]);

        $appClient = $this->createMock(GithubAppClient::class);
        $installationClient = $this->createMock(GithubAppClient::class);

        $installationClient->expects($this->never())
            ->method('authenticate');

        $appClient->method('apps')
            ->willReturn($mockAppsWrapper);

        $this->expectException(OutOfBoundsException::class);

        $installation = new GithubAppInstallationClient(
            $appClient,
            $installationClient
        );

        $installation->authenticateAsRepositoryOwner('test-owner');
    }
}
