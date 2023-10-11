<?php

namespace App\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Enum\EnvironmentVariable;
use App\Model\PublishableCoverageDataInterface;
use App\Query\Result\FileCoverageQueryResult;
use App\Query\Result\LineCoverageQueryResult;
use App\Query\Result\TagCoverageQueryResult;
use App\Service\CoverageAnalyserService;
use App\Service\EnvironmentService;
use Bref\Event\EventBridge\EventBridgeEvent;
use JsonException;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\JobState;
use Packages\Models\Enum\LineState;
use Packages\Models\Enum\PublishableCheckRunStatus;
use Packages\Models\Model\Event\JobStateChange;
use Packages\Models\Model\PublishableMessage\PublishableCheckAnnotationMessage;
use Packages\Models\Model\PublishableMessage\PublishableCheckRunMessage;
use Packages\Models\Model\PublishableMessage\PublishableMessageCollection;
use Packages\Models\Model\PublishableMessage\PublishablePullRequestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class JobStateChangeEventProcessor implements EventProcessorInterface
{
    /**
     * @param SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer
     */
    public function __construct(
        private readonly LoggerInterface $eventProcessorLogger,
        private readonly SerializerInterface $serializer,
        private readonly CoverageAnalyserService $coverageAnalyserService,
        private readonly GithubAppInstallationClient $githubAppInstallationClient,
        private readonly EnvironmentService $environmentService,
        private readonly SqsMessageClient $sqsEventClient,
        private readonly EventBridgeEventClient $eventBridgeEventService
    ) {
    }

    public function process(EventBridgeEvent $event): void
    {
        try {
            $jobStateChange = $this->serializer->denormalize(
                $event->getDetail(),
                JobStateChange::class
            );

            $this->eventProcessorLogger->info(
                sprintf(
                    'Starting analysis on %s.',
                    (string)$jobStateChange
                )
            );

            $coverageData = $this->coverageAnalyserService->analyse($jobStateChange);

            if (
                $jobStateChange->isInitialState() &&
                $jobStateChange->getIndex() === 0
            ) {
                $successful = $this->queueStartCheckRun($jobStateChange);
            } elseif (
                $jobStateChange->getState() === JobState::COMPLETED &&
                $this->isAllCheckRunsFinished(
                    $jobStateChange->getOwner(),
                    $jobStateChange->getRepository(),
                    $jobStateChange->getCommit()
                )
            ) {
                $successful = $this->queueFinalCheckRun(
                    $jobStateChange,
                    $coverageData
                );
            } else {
                return;
            }

            if (!$successful) {
                $this->eventProcessorLogger->critical(
                    sprintf(
                        'Attempt to publish coverage for %s was unsuccessful.',
                        (string)$jobStateChange
                    )
                );

                $this->eventBridgeEventService->publishEvent(
                    CoverageEvent::ANALYSE_FAILURE,
                    $jobStateChange
                );

                return;
            }
        } catch (JsonException $e) {
            $this->eventProcessorLogger->critical(
                'Exception while parsing event details.',
                [
                    'exception' => $e,
                    'event' => $event->toArray(),
                ]
            );
        }
    }

    public static function getProcessorEvent(): string
    {
        return CoverageEvent::JOB_STATE_CHANGE->value;
    }

    private function isAllCheckRunsFinished(
        string $owner,
        string $repository,
        string $ref
    ): bool {
        $this->githubAppInstallationClient->authenticateAsRepositoryOwner($owner);

        /** @var array{ check_runs: array{ status: string, app: array{ id: int } } }[] $checkRuns */
        $checkRuns = $this->githubAppInstallationClient->checkRuns()
            ->allForReference(
                $owner,
                $repository,
                $ref
            );

        foreach ($checkRuns['check_runs'] as $checkRun) {
            if (
                $checkRun['status'] !== 'completed' &&
                (string)$checkRun['app']['id'] !== $this->environmentService->getVariable(
                    EnvironmentVariable::GITHUB_APP_ID
                )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Write all of the publishable coverage data messages onto the queue for the _starting_ check
     * run state, ready to be picked up and published to the version control provider.
     *
     * Right now, this is:
     * 2. An in progress check run
     */
    private function queueStartCheckRun(JobStateChange $jobStateChange): bool
    {
        return $this->sqsEventClient->queuePublishableMessage(
            new PublishableCheckRunMessage(
                $jobStateChange,
                PublishableCheckRunStatus::IN_PROGRESS,
                [],
                0,
                $jobStateChange->getEventTime()
            )
        );
    }

    /**
     * Write all of the publishable coverage data messages onto the queue for the _final_ check
     * run state, ready to be picked up and published to the version control provider.
     *
     * Right now, this is:
     * 2. A complete check run
     * 3. A collection of check run annotations, linked to each uncovered line of
     *    the diff
     */
    private function queueFinalCheckRun(
        JobStateChange $jobStateChange,
        PublishableCoverageDataInterface $publishableCoverageData
    ): bool {
        $annotations = array_map(
            function (LineCoverageQueryResult $line) use ($jobStateChange, $publishableCoverageData) {
                if ($line->getState() !== LineState::UNCOVERED) {
                    return null;
                }

                return new PublishableCheckAnnotationMessage(
                    $jobStateChange,
                    $line->getFileName(),
                    $line->getLineNumber(),
                    $line->getState(),
                    $publishableCoverageData->getLatestSuccessfulUpload() ?? $jobStateChange->getEventTime()
                );
            },
            $publishableCoverageData->getDiffLineCoverage()->getLines()
        );

        return $this->sqsEventClient->queuePublishableMessage(
            new PublishableMessageCollection(
                $jobStateChange,
                [
                    new PublishablePullRequestMessage(
                        $jobStateChange,
                        $publishableCoverageData->getCoveragePercentage(),
                        $publishableCoverageData->getDiffCoveragePercentage(),
                        count($publishableCoverageData->getSuccessfulUploads()),
                        0,
                        array_map(
                            function (TagCoverageQueryResult $tag) {
                                return [
                                    'tag' => [
                                        'name' => $tag->getTag()->getName(),
                                        'commit' => $tag->getTag()->getCommit(),
                                    ],
                                    'coveragePercentage' => $tag->getCoveragePercentage(),
                                    'lines' => $tag->getLines(),
                                    'covered' => $tag->getCovered(),
                                    'partial' => $tag->getPartial(),
                                    'uncovered' => $tag->getUncovered(),
                                ];
                            },
                            $publishableCoverageData->getTagCoverage()->getTags()
                        ),
                        array_map(
                            function (FileCoverageQueryResult $file) {
                                return [
                                    'fileName' => $file->getFileName(),
                                    'coveragePercentage' => $file->getCoveragePercentage(),
                                    'lines' => $file->getLines(),
                                    'covered' => $file->getCovered(),
                                    'partial' => $file->getPartial(),
                                    'uncovered' => $file->getUncovered(),
                                ];
                            },
                            $publishableCoverageData->getLeastCoveredDiffFiles()->getFiles()
                        ),
                        $publishableCoverageData->getLatestSuccessfulUpload() ?? $upload->getIngestTime()
                    ),
                    new PublishableCheckRunMessage(
                        $jobStateChange,
                        PublishableCheckRunStatus::SUCCESS,
                        array_filter($annotations),
                        $publishableCoverageData->getCoveragePercentage(),
                        $publishableCoverageData->getLatestSuccessfulUpload() ?? $jobStateChange->getEventTime()
                    )
                ]
            ),
        );
    }
}
