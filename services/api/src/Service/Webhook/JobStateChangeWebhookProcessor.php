<?php

namespace App\Service\Webhook;

use App\Client\EventBridgeEventClient;
use App\Entity\Job;
use App\Entity\Project;
use App\Enum\EnvironmentVariable;
use App\Model\Webhook\PipelineStateChangeWebhookInterface;
use App\Model\Webhook\WebhookInterface;
use App\Repository\JobRepository;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use AsyncAws\Core\Exception\Http\HttpException;
use DateTimeImmutable;
use JsonException;
use Packages\Contracts\Event\Event;
use Packages\Event\Model\JobStateChange;
use Psr\Log\LoggerInterface;
use RuntimeException;

class JobStateChangeWebhookProcessor implements WebhookProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface $webhookProcessorLogger,
        private readonly JobRepository $jobRepository,
        private readonly EventBridgeEventClient $eventBridgeEventClient,
        private readonly EnvironmentServiceInterface $environmentService
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

        if ($webhook->getAppId() === $this->environmentService->getVariable(EnvironmentVariable::GITHUB_APP_ID)) {
            $this->webhookProcessorLogger->info(
                sprintf(
                    'Ignoring as webhook is a state change caused by us: %s. Current state of the job is: %s',
                    (string)$webhook,
                    $webhook->getJobState()->value
                )
            );

            return;
        }

        $this->webhookProcessorLogger->info(
            sprintf(
                'Processing pipeline state change (%s). Current state of the job is: %s',
                (string)$webhook,
                $webhook->getJobState()->value
            )
        );

        $job = $this->findOrCreateJob($project, $webhook);
        $isNewJob = $job->getId() === null;

        if (
            !$isNewJob &&
            $job->getState() === $webhook->getJobState()
        ) {
            // The state of the job hasn't actually changed, so theres no benefit
            // in updating the job or publishing an event.
            $this->webhookProcessorLogger->info(
                sprintf(
                    'Ignoring as job state has not changed: %s',
                    (string)$webhook
                ),
                [
                    'jobId' => $job->getId()
                ]
            );
            return;
        }

        $job->setState($webhook->getJobState());
        $job->setUpdatedAt(new DateTimeImmutable());

        $this->jobRepository->save($job, true);

        $this->webhookProcessorLogger->info(
            sprintf(
                'Job updated successfully based on webhook changes: %s.',
                (string)$webhook
            ),
            [
                'jobId' => $job->getId(),
                'isNewJob' => $isNewJob
            ]
        );

        $this->publishEvent(
            new JobStateChange(
                $webhook->getProvider(),
                $webhook->getOwner(),
                $webhook->getRepository(),
                $webhook->getRef(),
                $webhook->getCommit(),
                $webhook->getPullRequest(),
                $webhook->getExternalId(),
                $this->getJobIndex($job, $project, $webhook),
                $webhook->getJobState(),
                $webhook->getSuiteState(),
                $isNewJob,
                new DateTimeImmutable()
            )
        );
    }

    private function findOrCreateJob(
        Project $project,
        WebhookInterface&PipelineStateChangeWebhookInterface $webhook
    ): Job {
        $job = $this->jobRepository->findOneBy(
            [
                'project' => $project,
                'commit' => $webhook->getCommit(),
                'externalId' => $webhook->getExternalId()
            ]
        );

        if ($job === null) {
            return $this->jobRepository->create(
                $project,
                $webhook->getCommit(),
                (string)$webhook->getExternalId()
            );
        }

        return $job;
    }

    private function getJobIndex(Job $job, Project $project, PipelineStateChangeWebhookInterface $webhook): int
    {
        /** @var int|false $index */
        $index = array_search(
            $job,
            $this->getJobs($project, $webhook->getCommit()),
            true
        );

        if ($index === false) {
            throw new RuntimeException(
                sprintf(
                    'Failed to find job index for %s',
                    (string)$job
                )
            );
        }

        return $index;
    }

    private function getJobs(Project $project, string $commit): array
    {
        return $this->jobRepository->findBy(
            [
                'project' => $project,
                'commit' => $commit,
            ]
        );
    }

    private function publishEvent(JobStateChange $jobStateChange): void
    {
        try {
            $this->eventBridgeEventClient->publishEvent($jobStateChange);
        } catch (HttpException | JsonException $e) {
            $this->webhookProcessorLogger->error(
                sprintf(
                    'Failed to publish job state change event: %s',
                    (string)$jobStateChange
                ),
                [
                    'exception' => $e
                ]
            );
        }
    }

    public static function getEvent(): string
    {
        return Event::JOB_STATE_CHANGE->value;
    }
}
