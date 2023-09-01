<?php

namespace App\Service\Publisher\Github;

use App\Exception\PublishException;
use App\Service\EnvironmentService;
use App\Service\Formatter\CheckAnnotationFormatterService;
use App\Service\Formatter\CheckRunFormatterService;
use DateTimeImmutable;
use DateTimeInterface;
use Generator;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
use Packages\Models\Model\PublishableMessage\PublishableMessageInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class GithubCheckRunPublisherService extends AbstractGithubCheckPublisherService
{
    private const MAX_ANNOTATIONS_PER_CHECK_RUN = 50;

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
        $isNewCheckRun = false;
        $this->client->authenticateAsRepositoryOwner($owner);

        try {
            $checkRunId = $this->getCheckRun(
                $owner,
                $repository,
                $commit
            );
        } catch (RuntimeException) {
            $checkRunId = $this->createCheckRun(
                $owner,
                $repository,
                $commit,
                $publishableMessage->getCoveragePercentage(),
                []
            );
            $isNewCheckRun = true;
        }

        $chunkedAnnotations = $this->getFormattedAnnotations($publishableMessage);

        if (!$isNewCheckRun && $publishableMessage->getAnnotations() === []) {
            $this->updateCheckRun(
                $owner,
                $repository,
                $checkRunId,
                $publishableMessage->getCoveragePercentage(),
                []
            );
            return true;
        }

        foreach ($chunkedAnnotations as $chunk) {
            // Progressively update the check run with each new set of annotations. The API
            // is additive (i.e. non-idempotent) meaning by streaming new sets of annotations
            // they will be appended to the existing set.
            $this->updateCheckRun(
                $owner,
                $repository,
                $checkRunId,
                $publishableMessage->getCoveragePercentage(),
                $chunk
            );
        }

        return true;
    }

    private function createCheckRun(
        string $owner,
        string $repository,
        string $commit,
        float $coveragePercentage,
        array $annotations
    ): int {
        /** @var array{ id: string } $checkRun */
        $checkRun = $this->client->repo()
            ->checkRuns()
            ->create(
                $owner,
                $repository,
                [
                    'name' => sprintf('Coverage - %s%%', $coveragePercentage),
                    'head_sha' => $commit,
                    'status' => 'completed',
                    'conclusion' => 'success',
                    'annotations' => $annotations,
                    'completed_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                    'output' => [
                        'title' => $this->checkRunFormatterService->formatTitle(),
                        'summary' => $this->checkRunFormatterService->formatSummary(),
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

            throw new PublishException(
                sprintf(
                    'Failed to create check run. Status code was %s',
                    (string)$this->client->getLastResponse()?->getStatusCode()
                )
            );
        }

        return $checkRun['id'];
    }

    private function updateCheckRun(
        string $owner,
        string $repository,
        int $checkRunId,
        float $coveragePercentage,
        array $annotations
    ): bool {
        $this->client->repo()
            ->checkRuns()
            ->update(
                $owner,
                $repository,
                $checkRunId,
                [
                    'name' => sprintf('Coverage - %s%%', $coveragePercentage),
                    'status' => 'completed',
                    'conclusion' => 'success',
                    'annotations' => $annotations,
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

        return true;
    }

    /**
     * @psalm-type Annotation = array{
     *     annotation_level: 'warning',
     *     end_line: int,
     *     message: string,
     *     path: string,
     *     start_line: int,
     *     title: string
     * }
     * @return Generator<int, Annotation[]>
     */
    private function getFormattedAnnotations(PublishableCheckRunMessage $publishableMessage): iterable
    {
        /** @var Annotation[] $annotations */
        $annotations = [];

        foreach ($publishableMessage->getAnnotations() as $annotation) {
            if (count($annotations) === self::MAX_ANNOTATIONS_PER_CHECK_RUN) {
                yield $annotations;
                $annotations = [];
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

        if (!empty($annotations)) {
            yield $annotations;
        }
    }
}
