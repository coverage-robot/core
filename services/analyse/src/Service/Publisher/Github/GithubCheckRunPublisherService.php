<?php

namespace App\Service\Publisher\Github;

use App\Exception\PublishException;
use App\Model\PublishableCoverageDataInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class GithubCheckRunPublisherService extends AbstractGithubCheckPublisherService
{
    public function supports(Upload $upload, PublishableCoverageDataInterface $coverageData): bool
    {
        return $upload->getProvider() === Provider::GITHUB;
    }

    /**
     * Publish a check run to the PR, or commit, with the total coverage percentage.
     */
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

        try {
            $existingCheckRun = $this->getCheckRun(
                $owner,
                $repository,
                $commit
            );

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
                    ]
                );

            if ($this->client->getLastResponse()?->getStatusCode() !== Response::HTTP_OK) {
                $this->checkPublisherLogger->critical(
                    sprintf(
                        '%s status code returned while attempting to update existing check run with new results.',
                        (string)$this->client->getLastResponse()?->getStatusCode()
                    )
                );

                return false;
            }
        } catch (RuntimeException) {
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
                $this->checkPublisherLogger->critical(
                    sprintf(
                        '%s status code returned while attempting to create a new check run for results.',
                        (string)$this->client->getLastResponse()?->getStatusCode()
                    )
                );

                return false;
            }
        }

        return true;
    }

    public static function getPriority(): int
    {
        // The check run should **always** be published after the PR comment
        return GithubPullRequestCommentPublisherService::getPriority() - 1;
    }
}
