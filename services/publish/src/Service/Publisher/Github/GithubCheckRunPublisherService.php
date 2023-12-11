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
use Doctrine\Common\Annotations\Annotation;
use Generator;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Message\PublishableMessage\PublishableAnnotationInterface;
use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;
use Packages\Message\PublishableMessage\PublishableMessageInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * @psalm-type Annotation = array{
 *      annotation_level: 'warning',
 *      end_line: int,
 *      message: string,
 *      path: string,
 *      start_line: int,
 *      title: string
 *  }
 */
class GithubCheckRunPublisherService implements PublisherServiceInterface
{
    private const int MAX_ANNOTATIONS_PER_CHECK_RUN = 50;

    public function __construct(
        private readonly CheckRunFormatterService $checkRunFormatterService,
        private readonly CheckAnnotationFormatterService $checkAnnotationFormatterService,
        private readonly GithubAppInstallationClient $client,
        #[Autowire(service: EnvironmentService::class)]
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly LoggerInterface $checkPublisherLogger
    ) {
    }

    public function supports(PublishableMessageInterface $publishableMessage): bool
    {
        if (!$publishableMessage instanceof PublishableAnnotationInterface) {
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

    /**
     * Update an existing check run for the given commit, or create it if it doesnt exist.
     */
    private function upsertCheckRun(
        string $owner,
        string $repository,
        string $commit,
        PublishableCheckRunMessage $publishableMessage
    ): bool {
        $this->client->authenticateAsRepositoryOwner($owner);

        try {
            [$checkRunId, $currentAnnotations, $currentStatus] = $this->getCheckRun(
                $owner,
                $repository,
                $commit
            );

            if ($publishableMessage->getAnnotations() === []) {
                return $this->updateCheckRun(
                    $owner,
                    $repository,
                    $checkRunId,
                    $currentStatus,
                    $publishableMessage->getStatus(),
                    $publishableMessage->getCoveragePercentage(),
                    []
                );
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

        $chunkedAnnotations = $this->getFormattedAnnotations(
            $publishableMessage,
            $currentAnnotations ?? []
        );

        $successful = true;

        foreach ($chunkedAnnotations as $chunk) {
            // Progressively update the check run with each new set of annotations. The API
            // is additive (i.e. non-idempotent) meaning by streaming new sets of annotations
            // they will be appended to the existing set.
            $successful = $this->updateCheckRun(
                $owner,
                $repository,
                $checkRunId,
                $currentStatus,
                $publishableMessage->getStatus(),
                $publishableMessage->getCoveragePercentage(),
                $chunk
            ) && $successful;
        }

        return $successful;
    }

    /**
     * Create a new check run for the given commit, including publishing any annotations.
     */
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

    /**
     * Update an existing check run for the given commit, including publishing
     * any annotations which _are not_ already on the existing check run.
     */
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
     * @param Annotation[] $currentAnnotations
     * @return Generator<int, Annotation[]>
     */
    private function getFormattedAnnotations(
        PublishableCheckRunMessage $publishableMessage,
        array $currentAnnotations
    ): iterable {
        /** @var Annotation[] $annotations */
        $annotations = [];

        foreach ($this->filterAnnotations($publishableMessage, $currentAnnotations) as $annotation) {
            if (count($annotations) === self::MAX_ANNOTATIONS_PER_CHECK_RUN) {
                yield $annotations;
                $annotations = [];
            }

            $annotations[] = [
                'path' => $annotation->getFileName(),
                'annotation_level' => 'warning',
                'title' => $this->checkAnnotationFormatterService->formatTitle(),
                'message' => $this->checkAnnotationFormatterService->format($annotation),
                'start_line' => $annotation->getStartLineNumber(),
                'end_line' => $annotation->getEndLineNumber()
            ];
        }

        if (!empty($annotations)) {
            yield $annotations;
        }
    }

    /**
     * @param Annotation[] $currentAnnotations
     * @return PublishableAnnotationInterface[]
     */
    private function filterAnnotations(
        PublishableCheckRunMessage $publishableMessage,
        array $currentAnnotations
    ): array {
        return array_filter(
            $publishableMessage->getAnnotations(),
            static function (PublishableAnnotationInterface $annotation) use ($currentAnnotations) {
                foreach ($currentAnnotations as $currentAnnotation) {
                    if (
                        $annotation->getFileName() === $currentAnnotation['path'] &&
                        $annotation->getStartLineNumber() === $currentAnnotation['start_line']
                    ) {
                        return false;
                    }
                }

                return true;
            }
        );
    }

    /**
     * @return array{0: int, 1: Annotation[], 2: PublishableCheckRunStatus}
     */
    private function getCheckRun(string $owner, string $repository, string $commit): array
    {
        /**
         * @var array{
         *     id: int,
         *     conclusion: string|null,
         *     output: array{ annotations_count: non-negative-int },
         *     app: array{ id: int }
         * }[] $checkRuns
         */
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

        if ($checkRuns !== []) {
            $checkRun = reset($checkRuns);

            $checkRunId = $checkRun['id'];
            $totalAnnotations = $checkRun['output']['annotations_count'];
            $conclusion = $checkRun['conclusion'];

            $annotations = [];
            if ($totalAnnotations > 0) {
                // The check run already has published annotations, so we need
                // to de-duplicate them before we can publish the new ones.
                $annotations = $this->getCheckRunAnnotations(
                    $owner,
                    $repository,
                    $checkRunId
                );
            }

            return [
                $checkRunId,
                $annotations,
                $conclusion ?
                    PublishableCheckRunStatus::from($conclusion) :
                    PublishableCheckRunStatus::IN_PROGRESS
            ];
        }

        throw PublishException::notFoundException('check run');
    }

    /**
     * @return Annotation[]
     */
    private function getCheckRunAnnotations(
        string $owner,
        string $repository,
        int $checkRunId
    ): array {
        $checkRuns = $this->client->repo()
            ->checkRuns();

        $paginator = $this->client->pagination(100);

        /** @var Annotation[] $annotations */
        $annotations = $paginator->fetchAll(
            $checkRuns,
            'annotations',
            [$owner, $repository, $checkRunId]
        );

        return $annotations;
    }
}
