<?php

namespace App\Service\Webhook;

use App\Client\EventBridgeEventClient;
use App\Entity\Job;
use App\Entity\Project;
use App\Enum\JobState;
use App\Enum\WebhookProcessorEvent;
use App\Model\Webhook\PipelineStateChangeWebhookInterface;
use App\Model\Webhook\WebhookInterface;
use App\Repository\JobRepository;
use AsyncAws\Core\Exception\Http\HttpException;
use DateTimeImmutable;
use JsonException;
use Packages\Models\Enum\EventBus\CoverageEvent;
use Packages\Models\Model\Event\PipelineComplete;
use Psr\Log\LoggerInterface;
use RuntimeException;

class JobStateChangeWebhookProcessor implements WebhookProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface $webhookProcessorLogger,
        private readonly JobRepository $jobRepository,
        private readonly EventBridgeEventClient $eventBridgeEventClient
    ) {
    }

    /**
     * Process any webhooks received from third-party providers which relate to potential changes
     * in the state of a job (i.e. a CI pipeline job has completed).
     *
     * In practice this involves ingesting the modelled webhook payload and updating the state of
     * the associated job in the database. If theres no job associated with the ID from the webhook
     * one will be created.
     */
    public function process(Project $project, WebhookInterface $webhook): void
    {
        if (!$webhook instanceof PipelineStateChangeWebhookInterface) {
            throw new RuntimeException(
                sprintf(
                    'Webhook is not an instance of %s',
                    PipelineStateChangeWebhookInterface::class
                )
            );
        }

        $this->webhookProcessorLogger->info(
            sprintf(
                'Processing pipeline state change (%s). Current state of the job is: %s',
                (string)$webhook,
                $webhook->getJobState()->value
            )
        );

        $job = $this->findOrCreateJob($project, $webhook);

        $job->setState($webhook->getJobState());
        $job->setUpdatedAt(new DateTimeImmutable());

        $this->jobRepository->save($job, true);

        $this->webhookProcessorLogger->info(
            sprintf(
                'Job updated successfully based on webhook changes: %s.',
                (string)$webhook
            ),
            [
                'jobId' => $job->getId()
            ]
        );

        if ($this->isAllCommitJobsComplete($project, $webhook)) {
            $this->webhookProcessorLogger->info(
                sprintf(
                    'All jobs for %s are complete. Dispatching event.',
                    (string)$project
                )
            );

            $this->publishPipelineCompleteEvent($webhook);
        }
    }

    private function findOrCreateJob(
        Project $project,
        WebhookInterface&PipelineStateChangeWebhookInterface $webhook
    ): Job {
        $job = $this->jobRepository->findOneBy(
            [
                'project' => $project,
                'externalId' => $webhook->getExternalId()
            ]
        );

        if (!$job) {
            $job = new Job();
            $job->setProject($project);
            $job->setCommit($webhook->getCommit());
            $job->setExternalId((string)$webhook->getExternalId());

            $now = new DateTimeImmutable();
            $job->setCreatedAt($now);
            $job->setUpdatedAt($now);
        }

        return $job;
    }

    private function isAllCommitJobsComplete(
        Project $project,
        WebhookInterface&PipelineStateChangeWebhookInterface $webhook
    ): bool {
        if ($webhook->getSuiteState() !== JobState::COMPLETED) {
            // If the suite of jobs is not yet complete, it means we can expect
            // there to be at least one more job to be done
            return false;
        }

        return !$this->jobRepository->findOneBy(
            [
                'project' => $project,
                'commit' => $webhook->getCommit(),
                'state' => [
                    JobState::IN_PROGRESS,
                    JobState::PENDING,
                    JobState::QUEUED,
                ]
            ]
        );
    }

    private function publishPipelineCompleteEvent(WebhookInterface&PipelineStateChangeWebhookInterface $webhook): bool
    {
        try {
            return $this->eventBridgeEventClient->publishEvent(
                CoverageEvent::PIPELINE_COMPLETE,
                new PipelineComplete(
                    $webhook->getProvider(),
                    $webhook->getOwner(),
                    $webhook->getRepository(),
                    $webhook->getRef(),
                    $webhook->getCommit(),
                    $webhook->getPullRequest(),
                    new DateTimeImmutable()
                )
            );
        } catch (HttpException | JsonException $e) {
            $this->webhookProcessorLogger->error(
                sprintf(
                    'Failed to publish pipeline complete event for %s',
                    (string)$webhook
                ),
                [
                    'exception' => $e
                ]
            );
            return false;
        }
    }

    public static function getProcessorEvent(): string
    {
        return WebhookProcessorEvent::PIPELINE_STATE_CHANGE->value;
    }
}
