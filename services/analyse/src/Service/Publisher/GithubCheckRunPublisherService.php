<?php

namespace App\Service\Publisher;

use App\Client\Github\GithubAppClient;
use App\Client\Github\GithubAppInstallationClient;
use App\Enum\ProviderEnum;
use App\Model\PublishableCoverageDataInterface;
use App\Model\Upload;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

use function Amp\Promise\first;

class GithubCheckRunPublisherService implements PublisherServiceInterface
{
    public function __construct(
        private readonly GithubAppInstallationClient $client,
        private readonly LoggerInterface $publisherLogger
    ) {
    }

    public function supports(Upload $upload, PublishableCoverageDataInterface $coverageData): bool
    {
        return $upload->getProvider() === ProviderEnum::GITHUB;
    }

    public function publish(Upload $upload, PublishableCoverageDataInterface $coverageData): bool
    {
        $this->upsertCheckRun(
            $upload->getOwner(),
            $upload->getRepository(),
            $upload->getCommit(),
            $coverageData
        );

        return true;
    }

    private function upsertCheckRun(
        string $owner,
        string $repository,
        string $commit,
        PublishableCoverageDataInterface $coverageData
    ): bool {
        $this->client->authenticateAsRepositoryOwner($owner);

        $existingCheckRun = $this->getExistingCheckRunId(
            $owner,
            $repository,
            $commit
        );

        if (!$existingCheckRun) {
            $this->client->api('repo')
                ->checkRuns()
                ->create(
                    $owner,
                    $repository,
                    [
                        'name' => sprintf('Coverage - %s%%', $coverageData->getCoveragePercentage()),
                        'head_sha' => $commit,
                        'status' => 'completed',
                        'conclusion' => 'success',
                        'completed_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                        'output' => [
                            "title" => "Coverage",
                            "summary" => "Coverage Analysis Complete."
                        ]
                    ]
                );

            if ($this->client->getLastResponse()->getStatusCode() !== Response::HTTP_CREATED) {
                $this->publisherLogger->critical(
                    sprintf(
                        "%s status code returned while attempting to create a new check run for results.",
                        $this->client->getLastResponse()->getStatusCode()
                    )
                );

                return false;
            }

            return true;
        }

        $this->client->api('repo')
            ->checkRuns()
            ->update(
                $owner,
                $repository,
                $existingCheckRun,
                [
                    'name' => sprintf('Coverage - %s%%', $coverageData->getCoveragePercentage()),
                    'status' => 'completed',
                    'conclusion' => 'success',
                    'completed_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                    'output' => [
                        "title" => "Coverage",
                        "summary" => "Coverage Analysis Complete."
                    ]
                ]
            );

        if ($this->client->getLastResponse()->getStatusCode() !== Response::HTTP_OK) {
            $this->publisherLogger->critical(
                sprintf(
                    "%s status code returned while attempting to update existing check run with new results.",
                    $this->client->getLastResponse()->getStatusCode()
                )
            );

            return false;
        }

        return true;
    }

    private function getExistingCheckRunId(string $owner, string $repository, string $commit): ?string
    {
        $allCheckRuns = $this->client->api('repo')
            ->checkRuns()
            ->allForReference($owner, $repository, $commit);

        $checkRuns = array_filter(
            $allCheckRuns["check_runs"],
            static fn(array $checkRun) => isset($checkRun["id"]) &&
                isset($checkRun['app']['id']) &&
                $checkRun['app']['id'] == GithubAppClient::APP_ID
        );

        if (!empty($checkRuns)) {
            /** @var string $id */
            $id = first($checkRuns)["id"];

            return $id;
        }

        return null;
    }

    public static function getPriority(): int
    {
        // The check run should **always** be published after the PR comment
        return GithubPullRequestCommentPublisherService::getPriority() - 1;
    }
}
