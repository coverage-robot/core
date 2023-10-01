<?php

namespace App\Service\Publisher\Github;

use App\Enum\EnvironmentVariable;
use App\Exception\PublishException;
use App\Service\EnvironmentService;
use App\Service\Formatter\CheckAnnotationFormatterService;
use App\Service\Formatter\CheckRunFormatterService;
use App\Service\Publisher\PublisherServiceInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Generator;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Models\Enum\Provider;
use Packages\Models\Enum\PublishableCheckRunStatus;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
use Packages\Models\Model\PublishableMessage\PublishableMessageInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class GithubCheckRunPublisherService implements PublisherServiceInterface
{
    private const MAX_ANNOTATIONS_PER_CHECK_RUN = 50;

    public function __construct(
        private readonly CheckRunFormatterService $checkRunFormatterService,
        private readonly CheckAnnotationFormatterService $checkAnnotationFormatterService,
        private readonly GithubAppInstallationClient $client,
        private readonly EnvironmentService $environmentService,
        private readonly LoggerInterface $checkPublisherLogger
    ) {
    }

    public function supports(PublishableMessageInterface $publishableMessage): bool
    {
        if (!$publishableMessage instanceof PublishableCheckRunMessage) {
            return false;
        }

        return $publishableMessage->getEvent()->getProvider() === Provider::GITHUB;
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
        $event = $publishableMessage->getEvent();

        $successful = $this->upsertCheckRun(
            $event->getOwner(),
            $event->getRepository(),
            $event->getCommit(),
            $publishableMessage
        );

        if (!$successful) {
            $this->checkPublisherLogger->critical(
                sprintf(
                    'Failed to publish check run for %s',
                    (string)$event
                )
            );
        }

        return $successful;
    }

    private function upsertCheckRun(
        string $owner,
        string $repository,
        string $commit,
        PublishableCheckRunMessage $publishableMessage
    ): bool {
        $this->client->authenticateAsRepositoryOwner($owner);

        try {
            [$checkRunId, $currentStatus] = $this->getCheckRun(
                $owner,
                $repository,
                $commit
            );

            if ($publishableMessage->getAnnotations() === []) {
                $this->updateCheckRun(
                    $owner,
                    $repository,
                    $checkRunId,
                    $currentStatus,
                    $publishableMessage->getStatus(),
                    $publishableMessage->getCoveragePercentage(),
                    []
                );

                return true;
            }
        } catch (RuntimeException) {
            $checkRunId = $this->createCheckRun(
                $owner,
                $repository,
                $commit,
                $publishableMessage->getStatus(),
                $publishableMessage->getCoveragePercentage(),
                []
            );
            $currentStatus = $publishableMessage->getStatus();
        }

        $chunkedAnnotations = $this->getFormattedAnnotations($publishableMessage);

        foreach ($chunkedAnnotations as $chunk) {
            // Progressively update the check run with each new set of annotations. The API
            // is additive (i.e. non-idempotent) meaning by streaming new sets of annotations
            // they will be appended to the existing set.
            $this->updateCheckRun(
                $owner,
                $repository,
                $checkRunId,
                $currentStatus,
                $publishableMessage->getStatus(),
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
        ?PublishableCheckRunStatus $status,
        float $coveragePercentage,
        array $annotations
    ): int {
        $body = match ($status) {
            null => [
                'name' => 'Coverage Robot',
                'head_sha' => $commit,
                'annotations' => $annotations,
                'output' => [
                    'title' => $this->checkRunFormatterService->formatTitle(
                        PublishableCheckRunStatus::IN_PROGRESS,
                        $coveragePercentage
                    ),
                    'summary' => $this->checkRunFormatterService->formatSummary(),
                ]
            ],
            PublishableCheckRunStatus::IN_PROGRESS => [
                'name' => 'Coverage Robot',
                'head_sha' => $commit,
                'status' => $status->value,
                'annotations' => $annotations,
                'output' => [
                    'title' => $this->checkRunFormatterService->formatTitle($status, $coveragePercentage),
                    'summary' => $this->checkRunFormatterService->formatSummary(),
                ]
            ],
            default => [
                'name' => 'Coverage Robot',
                'head_sha' => $commit,
                'status' => 'completed',
                'conclusion' => $status->value,
                'annotations' => $annotations,
                'completed_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                'output' => [
                    'title' => $this->checkRunFormatterService->formatTitle($status, $coveragePercentage),
                    'summary' => $this->checkRunFormatterService->formatSummary(),
                ]
            ]
        };

        /** @var array{ id: string } $checkRun */
        $checkRun = $this->client->repo()
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

        return (int)$checkRun['id'];
    }

    private function updateCheckRun(
        string $owner,
        string $repository,
        int $checkRunId,
        ?PublishableCheckRunStatus $currentStatus,
        ?PublishableCheckRunStatus $status,
        float $coveragePercentage,
        array $annotations
    ): bool {
        $body = match ($status) {
            null => [
                'name' => 'Coverage Robot',
                'output' => [
                    'title' => $this->checkRunFormatterService->formatTitle(
                        $currentStatus ?? PublishableCheckRunStatus::IN_PROGRESS,
                        $coveragePercentage
                    ),
                    'summary' => $this->checkRunFormatterService->formatSummary(),
                    'annotations' => $annotations,
                ]
            ],
            PublishableCheckRunStatus::IN_PROGRESS => [
                'name' => 'Coverage Robot',
                'status' => $status->value,
                'output' => [
                    'title' => $this->checkRunFormatterService->formatTitle($status, $coveragePercentage),
                    'summary' => $this->checkRunFormatterService->formatSummary(),
                    'annotations' => $annotations,
                ]
            ],
            default => [
                'name' => 'Coverage Robot',
                'status' => 'completed',
                'conclusion' => $status->value,
                'output' => [
                    'title' => $this->checkRunFormatterService->formatTitle($status, $coveragePercentage),
                    'summary' => $this->checkRunFormatterService->formatSummary(),
                    'annotations' => $annotations,
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

    /**
     * @return array{0: int, 1: PublishableCheckRunStatus}
     */
    protected function getCheckRun(string $owner, string $repository, string $commit): array
    {
        /** @var array{ id: int, conclusion: string|null, app: array{ id: int } }[] $checkRuns */
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
            $conclusion = reset($checkRuns)['conclusion'];

            return [
                reset($checkRuns)['id'],
                $conclusion ?
                    PublishableCheckRunStatus::from($conclusion) :
                    PublishableCheckRunStatus::IN_PROGRESS
            ];
        }

        throw PublishException::notFoundException('check run');
    }
}
