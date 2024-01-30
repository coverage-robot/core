<?php

namespace Packages\Clients\Client\Github;

use Github\Api\GraphQL;
use Github\Api\Issue;
use Github\Api\PullRequest;
use Github\Api\Repo;
use Github\Api\Repository\Checks\CheckRuns;
use Github\ResultPager;
use Psr\Http\Message\ResponseInterface;

interface GithubAppInstallationClientInterface
{
    public function authenticateAsRepositoryOwner(string $owner): void;

    public function getLastResponse(): ?ResponseInterface;

    public function issue(): Issue;

    public function repo(): Repo;

    public function pullRequest(): PullRequest;

    public function checkRuns(): CheckRuns;

    public function graphql(): GraphQL;

    public function pagination(int $maxItems = 30): ResultPager;
}
