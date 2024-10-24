<?php

declare(strict_types=1);

namespace App\Webhook\Processor;

use App\Enum\WebhookProcessorEvent;
use App\Model\Webhook\PipelineStateChangeWebhookInterface;
use App\Model\Webhook\WebhookInterface;
use AsyncAws\Core\Exception\Http\HttpException;
use JsonException;
use Override;
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
        #[Autowire(service: EventBusClient::class)]
        private readonly EventBusClientInterface $eventBusClient
    ) {
    }

    /**
     * Process any webhooks received from third-party providers which relate to potential changes
     * in the state of a job (i.e. a CI pipeline job has completed).
     */
    #[Override]
    public function process(WebhookInterface $webhook): void
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

        $this->fireEvent(
            new JobStateChange(
                provider: $webhook->getProvider(),
                projectId: null,
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

    private function fireEvent(JobStateChange $jobStateChange): void
    {
        try {
            $this->eventBusClient->fireEvent($jobStateChange);
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
