<?php

namespace Packages\Clients\Client\Github;

use Github\Api\GraphQL;
use Github\Api\Issue;
use Github\Api\PullRequest;
use Github\Api\Repo;
use Github\AuthMethod;
use OutOfBoundsException;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

class GithubAppInstallationClient
{
    private ?string $owner = null;

    public function __construct(
        private readonly GithubAppClient $appClient,
        private readonly GithubAppClient $installationClient
    ) {
    }

    public function authenticateAsRepositoryOwner(string $owner): void
    {
        if ($owner === $this->owner) {
            return;
        }

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

    public function getLastResponse(): ResponseInterface
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

    public function graphql(): GraphQL
    {
        return new GraphQL($this->installationClient);
    }

    private function getInstallationForOwner(string $owner): int
    {
        /** @var array{ id: int, account: array{ login: string } }[] $installs */
        $installs = array_filter(
            $this->appClient->apps()
                ->findInstallations(),
            static fn(array $install) => isset($install['id'], $install['account']['login'])
                && $install['account']['login'] === $owner
        );

        if (empty($installs)) {
            throw new OutOfBoundsException('No installation with access to that account.');
        }

        return end($installs)['id'];
    }
}
