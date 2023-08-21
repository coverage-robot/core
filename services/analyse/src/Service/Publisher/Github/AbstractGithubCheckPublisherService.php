<?php

namespace App\Service\Publisher\Github;

use App\Client\Github\GithubAppClient;
use App\Client\Github\GithubAppInstallationClient;
use App\Enum\EnvironmentVariable;
use App\Exception\PublishException;
use App\Service\EnvironmentService;
use App\Service\Publisher\PublisherServiceInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractGithubCheckPublisherService implements PublisherServiceInterface
{
    public function __construct(
        protected readonly GithubAppInstallationClient $client,
        protected readonly EnvironmentService $environmentService,
        protected readonly LoggerInterface $checkPublisherLogger
    ) {
    }

    protected function getCheckRun(string $owner, string $repository, string $commit): int
    {
        /** @var array{ id: int, app: array{ id: string } }[] $checkRuns */
        $checkRuns = $this->client->repo()
            ->checkRuns()
            ->allForReference($owner, $repository, $commit)['check_runs'];

        $checkRuns = array_filter(
            $checkRuns,
            fn(array $checkRun) => isset($checkRun['id'], $checkRun['app']['id']) &&
                $checkRun['app']['id'] === $this->environmentService->getVariable(EnvironmentVariable::GITHUB_APP_ID)
        );

        if (!empty($checkRuns)) {
            return reset($checkRuns)['id'];
        }

        throw PublishException::notFoundException('check run');
    }
}
