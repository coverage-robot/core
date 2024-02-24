<?php

namespace Packages\Clients\Client\Github;

use Github\Api\GraphQL;
use Github\Api\Issue;
use Github\Api\PullRequest;
use Github\Api\Repo;
use Github\Api\Repository\Checks\CheckRuns;
use Github\AuthMethod;
use Github\Client;
use Github\ResultPager;
use OutOfBoundsException;
use Packages\Telemetry\Service\MetricServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use UnexpectedValueException;

final class GithubAppInstallationClient implements GithubAppInstallationClientInterface
{
    private ?string $owner = null;

    public function __construct(
        #[Autowire(service: GithubAppClient::class)]
        private readonly Client $appClient,
        #[Autowire(service: GithubAppClient::class)]
        private readonly Client $installationClient,
        private readonly MetricServiceInterface $metricService
    ) {
    }

    public function authenticateAsRepositoryOwner(string $owner): void
    {
        if ($owner === $this->owner) {
            return;
        }

        $this->metricService->increment(
            metric: 'GithubAppInstallationTokenRequests',
            dimensions: [[$owner]],
            properties: ['owner' => $owner]
        );

        $accessToken = $this->appClient->apps()
            ->createInstallationToken($this->getInstallationForOwner($owner));

        if (!isset($accessToken['token']) || !is_string($accessToken['token'])) {
            throw new UnexpectedValueException('Unable to generate access token for installation.');
        }

        $this->installationClient->authenticate(
            $accessToken['token'],
            null,
            AuthMethod::ACCESS_TOKEN
        );

        $this->owner = $owner;
    }

    public function getLastResponse(): ?ResponseInterface
    {
        return $this->installationClient->getLastResponse();
    }

    public function issue(): Issue
    {
        return new Issue($this->installationClient);
    }

    public function repo(): Repo
    {
        return new Repo($this->installationClient);
    }

    public function pullRequest(): PullRequest
    {
        return new PullRequest($this->installationClient);
    }

    public function checkRuns(): CheckRuns
    {
        return new CheckRuns($this->installationClient);
    }

    public function graphql(): GraphQL
    {
        return new GraphQL($this->installationClient);
    }

    public function pagination(int $maxItems = 30): ResultPager
    {
        return new ResultPager($this->installationClient, $maxItems);
    }

    private function getInstallationForOwner(string $owner): int
    {
        /** @var array{ id: int, account: array{ login: string } }[] $installs */
        $installs = array_filter(
            $this->appClient->apps()
                ->findInstallations(),
            static fn(array $install): bool => isset($install['id'], $install['account']['login'])
                && $install['account']['login'] === $owner
        );

        if (empty($installs)) {
            throw new OutOfBoundsException('No installation with access to that account.');
        }

        return end($installs)['id'];
    }
}
