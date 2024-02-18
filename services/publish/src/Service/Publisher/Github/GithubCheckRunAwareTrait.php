<?php

namespace App\Service\Publisher\Github;

use App\Enum\EnvironmentVariable;
use App\Enum\TemplateVariant;
use App\Exception\PublishException;
use DateTimeImmutable;
use DateTimeInterface;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use Symfony\Component\HttpFoundation\Response;

trait GithubCheckRunAwareTrait
{
    /**
     * Create a new check run for the given commit.
     */
    private function createCheckRun(
        string $owner,
        string $repository,
        string $commit,
        PublishableCheckRunMessage $publishableMessage
    ): bool {
        $body = match ($publishableMessage->getStatus()) {
            PublishableCheckRunStatus::IN_PROGRESS => [
                'name' => 'Coverage Robot',
                'head_sha' => $commit,
                'status' => $publishableMessage->getStatus()->value,
                'annotations' => [],
                'output' => [
                    'title' => $this->templateRenderingService->render(
                        $publishableMessage,
                        TemplateVariant::IN_PROGRESS_CHECK_RUN
                    ),
                    'summary' => '',
                ]
            ],
            PublishableCheckRunStatus::SUCCESS, PublishableCheckRunStatus::FAILURE => [
                'name' => 'Coverage Robot',
                'head_sha' => $commit,
                'status' => 'completed',
                'conclusion' => $publishableMessage->getStatus()->value,
                'annotations' => [],
                'completed_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                'output' => [
                    'title' => $this->templateRenderingService->render(
                        $publishableMessage,
                        TemplateVariant::COMPLETE_CHECK_RUN
                    ),
                    'summary' => '',
                ]
            ]
        };

        /** @var array{ id: string } $checkRun */
        $this->client->repo()
            ->checkRuns()
            ->create(
                $owner,
                $repository,
                $body
            );

        if ($this->client->getLastResponse()?->getStatusCode() !== Response::HTTP_CREATED) {
            $this->checkPublisherLogger->critical(
                sprintf(
                    '%s status code returned while attempting to create a new check run for results.',
                    (string)$this->client->getLastResponse()?->getStatusCode()
                )
            );

            throw new PublishException(
                sprintf(
                    'Failed to create check run. Status code was %s',
                    (string)$this->client->getLastResponse()?->getStatusCode()
                )
            );
        }

        return true;
    }

    /**
     * Update an existing check run for the given commit.
     */
    private function updateCheckRun(
        string $owner,
        string $repository,
        int $checkRunId,
        PublishableCheckRunMessage $publishableMessage
    ): bool {
        $body = match ($publishableMessage->getStatus()) {
            PublishableCheckRunStatus::IN_PROGRESS => [
                'name' => 'Coverage Robot',
                'status' => $publishableMessage->getStatus()->value,
                'output' => [
                    'title' => $this->templateRenderingService->render(
                        $publishableMessage,
                        TemplateVariant::IN_PROGRESS_CHECK_RUN
                    ),
                    'summary' => '',
                    'annotations' => [],
                ]
            ],
            PublishableCheckRunStatus::SUCCESS, PublishableCheckRunStatus::FAILURE => [
                'name' => 'Coverage Robot',
                'status' => 'completed',
                'conclusion' => $publishableMessage->getStatus()->value,
                'output' => [
                    'title' => $this->templateRenderingService->render(
                        $publishableMessage,
                        TemplateVariant::COMPLETE_CHECK_RUN
                    ),
                    'summary' => '',
                    'annotations' => [],
                ],
                'completed_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            ]
        };

        $this->client->repo()
            ->checkRuns()
            ->update(
                $owner,
                $repository,
                $checkRunId,
                $body
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

        return true;
    }

    /**
     * @return array{
     *      id: int,
     *      conclusion: string|null,
     *      output: array{
     *          title: string,
     *          summary: string,
     *          annotations_count: non-negative-int
     *      }
     *  }[]
     */
    private function getCheckRun(string $owner, string $repository, string $commit): array
    {
        $appId = $this->environmentService->getVariable(EnvironmentVariable::GITHUB_APP_ID);

        /**
         * @var  $checkRuns
         */
        $checkRuns = $this->client->repo()
            ->checkRuns()
            ->allForReference(
                $owner,
                $repository,
                $commit,
                [
                    'app_id' => $appId
                ]
            )['check_runs'];

        if ($checkRuns !== []) {
            return reset($checkRuns);
        }

        throw PublishException::notFoundException('check run');
    }
}
