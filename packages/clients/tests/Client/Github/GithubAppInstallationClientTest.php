<?php

namespace Packages\Clients\Tests\Client\Github;

use Github\Api\Apps;
use Github\Api\GraphQL;
use Github\Api\Issue;
use Github\Api\PullRequest;
use Github\Api\Repo;
use Github\Api\Repository\Checks\CheckRuns;
use Github\AuthMethod;
use Github\ResultPager;
use OutOfBoundsException;
use Packages\Clients\Client\Github\GithubAppClient;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

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

    public function testCommonEndpointsAreAvailable(): void
    {
        $client = new GithubAppInstallationClient(
            $this->createMock(GithubAppClient::class),
            $this->createMock(GithubAppClient::class)
        );

        $this->assertInstanceOf(Issue::class, $client->issue());
        $this->assertInstanceOf(Repo::class, $client->repo());
        $this->assertInstanceOf(PullRequest::class, $client->pullRequest());
        $this->assertInstanceOf(CheckRuns::class, $client->checkRuns());
        $this->assertInstanceOf(GraphQL::class, $client->graphql());
        $this->assertInstanceOf(ResultPager::class, $client->pagination());
    }

    public function testGettingLastResponse(): void
    {
        $mockLastResponse = $this->createMock(ResponseInterface::class);

        $installationClient = $this->createMock(GithubAppClient::class);
        $client = new GithubAppInstallationClient(
            $this->createMock(GithubAppClient::class),
            $installationClient
        );

        $installationClient->expects($this->once())
            ->method('getLastResponse')
            ->willReturn($mockLastResponse);

        $this->assertEquals(
            $mockLastResponse,
            $client->getLastResponse()
        );
    }
}
