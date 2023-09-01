<?php

namespace App\Service\Publisher\Github;

use App\Enum\EnvironmentVariable;
use App\Exception\PublishException;
use App\Service\EnvironmentService;
use App\Service\Publisher\PublisherServiceInterface;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
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
        /** @var array{ id: int, app: array{ id: int } }[] $checkRuns */
        $checkRuns = $this->client->repo()
            ->checkRuns()
            ->allForReference($owner, $repository, $commit)['check_runs'];

        $checkRuns = array_filter(
            $checkRuns,
            fn(array $checkRun) => isset($checkRun['id'], $checkRun['app']['id']) &&
                (string)$checkRun['app']['id'] === $this->environmentService->getVariable(
                    EnvironmentVariable::GITHUB_APP_ID
                )
        );

        if (!empty($checkRuns)) {
            return reset($checkRuns)['id'];
        }

        throw PublishException::notFoundException('check run');
    }
}
