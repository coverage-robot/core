<?php

namespace App\Service\Publisher\Github;

use App\Exception\PublishException;
use App\Service\EnvironmentService;
use App\Service\Formatter\CheckRunFormatterService;
use DateTimeImmutable;
use DateTimeInterface;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
use Packages\Models\Model\PublishableMessage\PublishableMessageInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class GithubCheckRunPublisherService extends AbstractGithubCheckPublisherService
{
    public function __construct(
        private readonly CheckRunFormatterService $checkRunFormatterService,
        GithubAppInstallationClient $client,
        EnvironmentService $environmentService,
        LoggerInterface $checkPublisherLogger
    ) {
        parent::__construct($client, $environmentService, $checkPublisherLogger);
    }

    public function supports(PublishableMessageInterface $publishableMessage): bool
    {
        if (!$publishableMessage instanceof PublishableCheckRunMessage) {
            return false;
        }

        return $publishableMessage->getUpload()->getProvider() === Provider::GITHUB;
    }

    /**
     * Publish a check run to the PR, or commit, with the total coverage percentage.
     */
    public function publish(PublishableMessageInterface $publishableMessage): bool
    {
        if (!$this->supports($publishableMessage)) {
            throw PublishException::notSupportedException();
        }

        /** @var PublishableCheckRunMessage $publishableMessage */
        $upload = $publishableMessage->getUpload();

        $this->upsertCheckRun(
            $upload->getOwner(),
            $upload->getRepository(),
            $upload->getCommit(),
            $publishableMessage
        );

        return true;
    }

    private function upsertCheckRun(
        string $owner,
        string $repository,
        string $commit,
        PublishableCheckRunMessage $publishableMessage
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
                        'name' => sprintf('Coverage - %s%%', $publishableMessage->getCoveragePercentage()),
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
                        'name' => sprintf('Coverage - %s%%', $publishableMessage->getCoveragePercentage()),
                        'head_sha' => $commit,
                        'status' => 'completed',
                        'conclusion' => 'success',
                        'completed_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                        'output' => [
                            'title' => $this->checkRunFormatterService->formatTitle(),
                            'summary' => $this->checkRunFormatterService->formatSummary()
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
}
