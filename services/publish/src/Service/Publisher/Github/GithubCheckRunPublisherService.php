<?php

namespace App\Service\Publisher\Github;

use App\Exception\PublishException;
use App\Service\EnvironmentService;
use App\Service\Formatter\CheckAnnotationFormatterService;
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
        private readonly CheckAnnotationFormatterService $checkAnnotationFormatterService,
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

        $successful = $this->upsertCheckRun(
            $upload->getOwner(),
            $upload->getRepository(),
            $upload->getCommit(),
            $publishableMessage
        );

        if (!$successful) {
            $this->checkPublisherLogger->critical(
                sprintf(
                    'Failed to publish check run for %s',
                    (string)$upload
                )
            );

            return false;
        }

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
            $checkRunId = $this->getCheckRun(
                $owner,
                $repository,
                $commit
            );
        } catch (RuntimeException) {
            $checkRun = $api->checkRuns()
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

            $checkRunId = $checkRun['id'];
        }

        $chunkedAnnotations = $this->getFormattedAnnotations($publishableMessage);

        foreach ($chunkedAnnotations as $chunk) {
            $api->checkRuns()
                ->update(
                    $owner,
                    $repository,
                    $checkRunId,
                    [
                        'name' => sprintf('Coverage - %s%%', $publishableMessage->getCoveragePercentage()),
                        'status' => 'completed',
                        'conclusion' => 'success',
                        'annotations' => $chunk,
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
        }

        return true;
    }

    private function getFormattedAnnotations(PublishableCheckRunMessage $publishableMessage): iterable
    {
        $annotations = [];

        foreach ($publishableMessage->getAnnotations() as $annotation) {
            if (count($annotations) === 50) {
                yield $annotations;
                $annotations = 0;
            }

            $annotations[] = [
                'path' => $annotation->getFileName(),
                'annotation_level' => 'warning',
                'title' => $this->checkAnnotationFormatterService->formatTitle($annotation),
                'message' => $this->checkAnnotationFormatterService->format($annotation),
                'start_line' => $annotation->getLineNumber(),
                'end_line' => $annotation->getLineNumber()
            ];
        }

        yield $annotations;
    }
}
