<?php

namespace App\Service\Publisher;

use App\Client\Github\GithubAppClient;
use App\Client\Github\GithubAppInstallationClient;
use App\Exception\PublishException;
use App\Model\PublishableCoverageDataInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Packages\Models\Enum\ProviderEnum;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class GithubCheckRunPublisherService implements PublisherServiceInterface
{
    public function __construct(
        private readonly GithubAppInstallationClient $client,
        private readonly LoggerInterface $checkRunPublisherLogger
    ) {
    }

    public function supports(Upload $upload, PublishableCoverageDataInterface $coverageData): bool
    {
        return $upload->getProvider() === ProviderEnum::GITHUB;
    }

    public function publish(Upload $upload, PublishableCoverageDataInterface $coverageData): bool
    {
        if (!$this->supports($upload, $coverageData)) {
            throw PublishException::notSupportedException();
        }

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

        $api = $this->client->repo();

        $existingCheckRun = $this->getExistingCheckRunId(
            $owner,
            $repository,
            $commit
        );

        if (!$existingCheckRun) {
            $api->checkRuns()
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
                            'title' => 'Coverage',
                            'summary' => 'Coverage Analysis Complete.'
                        ]
                    ]
                );

            if ($this->client->getLastResponse()?->getStatusCode() !== Response::HTTP_CREATED) {
                $this->checkRunPublisherLogger->critical(
                    sprintf(
                        '%s status code returned while attempting to create a new check run for results.',
                        (string)$this->client->getLastResponse()?->getStatusCode()
                    )
                );

                return false;
            }

            return true;
        }

        $api->checkRuns()
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
                        'title' => 'Coverage',
                        'summary' => 'Coverage Analysis Complete.'
                    ]
                ]
            );

        if ($this->client->getLastResponse()?->getStatusCode() !== Response::HTTP_OK) {
            $this->checkRunPublisherLogger->critical(
                sprintf(
                    '%s status code returned while attempting to update existing check run with new results.',
                    (string)$this->client->getLastResponse()?->getStatusCode()
                )
            );

            return false;
        }

        return true;
    }

    private function getExistingCheckRunId(string $owner, string $repository, string $commit): ?int
    {
        $api = $this->client->repo();

        /** @var array{ id: int, app: array{ id: string } }[] $checkRuns */
        $checkRuns = $api->checkRuns()->allForReference($owner, $repository, $commit)['check_runs'] ?? [];
        $checkRuns = array_filter(
            $checkRuns,
            static fn(array $checkRun) => isset($checkRun['id']) &&
                isset($checkRun['app']['id']) &&
                $checkRun['app']['id'] == GithubAppClient::APP_ID
        );

        if (!empty($checkRuns)) {
            return reset($checkRuns)['id'];
        }

        return null;
    }

    public static function getPriority(): int
    {
        // The check run should **always** be published after the PR comment
        return GithubPullRequestCommentPublisherService::getPriority() - 1;
    }
}
