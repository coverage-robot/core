<?php

namespace App\Service\Event;

use App\Client\EventBridgeEventClient;
use App\Client\SqsMessageClient;
use App\Enum\EnvironmentVariable;
use App\Model\PublishableCoverageDataInterface;
use App\Query\Result\LineCoverageQueryResult;
use App\Service\CoverageAnalyserService;
use App\Service\EnvironmentService;
use Bref\Event\EventBridge\EventBridgeEvent;
use DateTime;
use DateTimeImmutable;
use Exception;
use JsonException;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Enum\JobState;
use Packages\Models\Enum\LineState;
use Packages\Models\Enum\PublishableCheckRunStatus;
use Packages\Models\Model\Event\CoverageFinalised;
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
                    'Starting to process %s.',
                    (string)$jobStateChange
                )
            );

            $coverageData = $this->coverageAnalyserService->analyse($jobStateChange);

            if (
                $jobStateChange->isInitialState() &&
                $jobStateChange->getIndex() === 0
            ) {
                $this->eventProcessorLogger->info(
                    sprintf(
                        '%s is the initial job. Queuing up the initial check run to be published.',
                        (string)$jobStateChange
                    )
                );

                $successful = $this->queueStartCheckRun($jobStateChange);
            } elseif (
                $jobStateChange->getState() === JobState::COMPLETED &&
                $this->isAllCheckRunsFinished($jobStateChange)
            ) {
                $this->eventProcessorLogger->info(
                    sprintf(
                        '%s is the final job. Queuing up the final check run to be published.',
                        (string)$jobStateChange
                    )
                );

                $successful = $this->queueFinalCheckRun(
                    $jobStateChange,
                    $coverageData
                );

                $this->eventBridgeEventService->publishEvent(
                    CoverageEvent::COVERAGE_FINALISED,
                    new CoverageFinalised(
                        $jobStateChange->getProvider(),
                        $jobStateChange->getOwner(),
                        $jobStateChange->getRepository(),
                        $jobStateChange->getRef(),
                        $jobStateChange->getCommit(),
                        $jobStateChange->getPullRequest(),
                        $coverageData->getCoveragePercentage(),
                        new DateTimeImmutable()
                    )
                );
            } else {
                $this->eventProcessorLogger->info(
                    sprintf(
                        'Ignoring %s as it is not the start or end check run.',
                        (string)$jobStateChange
                    )
                );

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

    /**
     * Check if all of the check runs for the given job state change are finished,
     * so that results can be published in one batch.
     *
     * This factors in Check runs in GitHub (and their state) and then uses the job's
     * external Id to work out whether its the last job state change coming through.
     *
     * @throws Exception
     */
    private function isAllCheckRunsFinished(JobStateChange $jobStateChange): bool
    {
        $this->githubAppInstallationClient->authenticateAsRepositoryOwner($jobStateChange->getOwner());

        /** @var array{
         *     check_runs: array{
         *          id: string,
         *          completed_at: string,
         *          status: string,
         *          app: array{ id: int }
         *     }
         * }[] $checkRuns
         */
        $checkRuns = $this->githubAppInstallationClient->checkRuns()
            ->allForReference(
                $jobStateChange->getOwner(),
                $jobStateChange->getRepository(),
                $jobStateChange->getCommit()
            );

        $latestCompleteCheckRun = null;
        $isLatestCheckRun = false;

        foreach ($checkRuns['check_runs'] as $checkRun) {
            if (
                (string)$checkRun['app']['id'] === $this->environmentService->getVariable(
                    EnvironmentVariable::GITHUB_APP_ID
                )
            ) {
                // Ignore the check run if its ours!
                continue;
            }

            if ($checkRun['status'] !== 'completed') {
                // Not all the checks are complete yet. We're expecting more to
                // come through shortly.
                return false;
            }

            $completedAt = new DateTime($checkRun['completed_at']);

            if (
                !$latestCompleteCheckRun ||
                $completedAt > $latestCompleteCheckRun
            ) {
                $latestCompleteCheckRun = $completedAt;
                $isLatestCheckRun = $checkRun['id'] == $jobStateChange->getExternalId();
            }
        }

        if (!$isLatestCheckRun) {
            // Theres no other in progress check runs, but the job change we're handing _isnt_
            // the last one to complete. As such, we're confident theres another job state change
            // being handled, so can wait for that to occur.
            $this->eventProcessorLogger->info(
                sprintf(
                    'All check runs are complete for %s, but the job is not the last one to have changed.',
                    (string)$jobStateChange
                ),
                [
                    'latest' => $latestCompleteCheckRun
                ]
            );

            return false;
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
                        (array)$this->serializer->normalize($publishableCoverageData->getTagCoverage()->getTags()),
                        (array)$this->serializer->normalize(
                            $publishableCoverageData->getLeastCoveredDiffFiles()->getFiles()
                        ),
                        $publishableCoverageData->getLatestSuccessfulUpload() ?? $jobStateChange->getEventTime()
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
