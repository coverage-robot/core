<?php

namespace App\Webhook\Processor;

use App\Entity\Job;
use App\Entity\Project;
use App\Enum\WebhookProcessorEvent;
use App\Model\Webhook\PipelineStateChangeWebhookInterface;
use App\Model\Webhook\WebhookInterface;
use App\Repository\JobRepository;
use AsyncAws\Core\Exception\Http\HttpException;
use DateTimeImmutable;
use JsonException;
use Override;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Event\EventSource;
use Packages\Event\Client\EventBusClient;
use Packages\Event\Client\EventBusClientInterface;
use Packages\Event\Model\JobStateChange;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class JobStateChangeWebhookProcessor implements WebhookProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface $webhookProcessorLogger,
        private readonly JobRepository $jobRepository,
        #[Autowire(service: EventBusClient::class)]
        private readonly EventBusClientInterface $eventBusClient,
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
    #[Override]
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
                'jobId' => $job->getId(),
                'isNewJob' => $job->getId() === null
            ]
        );

        $this->fireEvent(
            new JobStateChange(
                provider: $webhook->getProvider(),
                owner: $webhook->getOwner(),
                repository: $webhook->getRepository(),
                ref: $webhook->getRef(),
                commit: $webhook->getCommit(),
                parent: array_filter([$webhook->getParent()]),
                externalId: $webhook->getExternalId(),
                triggeredByExternalId: $webhook->getAppId(),
                state: $webhook->getJobState(),
                pullRequest: $webhook->getPullRequest(),
                baseCommit: $webhook->getBaseCommit(),
                baseRef: $webhook->getBaseRef(),
                eventTime: $webhook->getEventTime()
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

    private function fireEvent(JobStateChange $jobStateChange): void
    {
        try {
            $this->eventBusClient->fireEvent(
                EventSource::API,
                $jobStateChange
            );
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

    #[Override]
    public static function getEvent(): string
    {
        return WebhookProcessorEvent::JOB_STATE_CHANGE->value;
    }
}
