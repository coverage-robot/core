<?php

namespace App\Service\Webhook;

use App\Client\EventBridgeEventClient;
use App\Entity\Job;
use App\Entity\Project;
use App\Enum\JobState;
use App\Enum\WebhookProcessorEvent;
use App\Model\Webhook\AbstractWebhook;
use App\Model\Webhook\Github\PipelineStateChangeWebhookInterface;
use App\Repository\JobRepository;
use App\Repository\ProjectRepository;
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
        private readonly ProjectRepository $projectRepository,
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
    public function process(AbstractWebhook $webhook): void
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

        $project = $this->getProject($webhook);

        if (!$project) {
            $this->webhookProcessorLogger->error(
                sprintf(
                    'No project found for webhook: %s',
                    (string)$webhook
                )
            );

            return;
        }

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

            $this->publishPipelineCompleteEvent($project, $webhook);
        }
    }

    private function getProject(AbstractWebhook $webhook): ?Project
    {
        return $this->projectRepository->findOneBy(
            [
                'provider' => $webhook->getProvider()->value,
                'owner' => $webhook->getOwner(),
                'repository' => $webhook->getRepository()
            ]
        );
    }

    private function findOrCreateJob(Project $project, AbstractWebhook $webhook): Job
    {
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
            $job->setExternalId($webhook->getExternalId());
            $job->setCreatedAt(new DateTimeImmutable());
            $job->setUpdatedAt($job->getCreatedAt());
        }

        return $job;
    }

    private function isAllCommitJobsComplete(Project $project, PipelineStateChangeWebhookInterface $webhook): bool
    {
        return !$this->jobRepository->findOneBy(
            [
                'project' => $project,
                'commit' => $webhook->getCommit(),
                'state' => [
                    JobState::WAITING,
                    JobState::PENDING,
                ]
            ]
        );
    }

    public function publishPipelineCompleteEvent(Project $project, PipelineStateChangeWebhookInterface $webhook): bool
    {
        try {
            return $this->eventBridgeEventClient->publishEvent(
                CoverageEvent::PIPELINE_COMPLETE,
                new PipelineComplete(
                    $project->getProvider(),
                    $project->getOwner(),
                    $project->getRepository(),
                    $webhook->getCommit(),
                    $webhook->getPullRequest()
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
