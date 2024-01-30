<?php

namespace App\Handler;

use App\Entity\Project;
use App\Exception\InvalidWebhookException;
use App\Model\Webhook\WebhookInterface;
use App\Repository\ProjectRepository;
use App\Service\WebhookProcessorService;
use App\Service\WebhookProcessorServiceInterface;
use App\Service\WebhookValidationService;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;
use Override;
use Packages\Telemetry\Enum\Unit;
use Packages\Telemetry\Service\MetricService;
use Packages\Telemetry\Service\TraceContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class WebhookHandler extends SqsHandler
{
    /**
     * @param SerializerInterface&DenormalizerInterface&NormalizerInterface $serializer
     */
    public function __construct(
        #[Autowire(service: WebhookProcessorService::class)]
        private readonly WebhookProcessorServiceInterface $webhookProcessor,
        private readonly LoggerInterface $webhookLogger,
        private readonly ProjectRepository $projectRepository,
        private readonly SerializerInterface $serializer,
        private readonly WebhookValidationService $webhookValidationService,
        private readonly MetricService $metricService
    ) {
    }

    /**
     * @throws InvalidLambdaEvent
     */
    #[Override]
    public function handleSqs(SqsEvent $event, Context $context): void
    {
        TraceContext::setTraceHeaderFromContext($context);

        $this->metricService->put(
            metric: 'ProcessableWebhooks',
            value: count($event->getRecords()),
            unit: Unit::COUNT
        );

        foreach ($event->getRecords() as $sqsRecord) {
            try {
                $webhook = $this->serializer->deserialize(
                    $sqsRecord->getBody(),
                    WebhookInterface::class,
                    'json'
                );

                $this->webhookValidationService->validate($webhook);
            } catch (ExceptionInterface | InvalidWebhookException $e) {
                $this->webhookLogger->error(
                    'Failed to deserialize webhook payload.',
                    [
                        'exception' => $e,
                        'payload' => $sqsRecord->getBody()
                    ]
                );

                $this->metricService->put(
                    metric: 'InvalidWebhooks',
                    value: 1,
                    unit: Unit::COUNT
                );

                continue;
            }

            $this->processWebhookEvent($webhook);
        }
    }


    /**
     * Process the incoming webhook event payload.
     */
    private function processWebhookEvent(WebhookInterface $webhook): void
    {
        $project = $this->getProject($webhook);

        if (!$project instanceof Project) {
            $this->metricService->put(
                metric: 'InvalidWebhooks',
                value: 1,
                unit: Unit::COUNT
            );
            return;
        }

        $this->webhookProcessor->process($project, $webhook);

        $this->metricService->put(
            metric: 'ValidWebhooks',
            value: 1,
            unit: Unit::COUNT
        );
    }

    /**
     * Validate that the project the webhook is for is present and enabled.
     */
    private function getProject(WebhookInterface $webhook): ?Project
    {
        $project = $this->projectRepository
            ->findOneBy(
                [
                    'provider' => $webhook->getProvider(),
                    'repository' => $webhook->getRepository(),
                    'owner' => $webhook->getOwner(),
                ]
            );

        if (!$project || !$project->isEnabled()) {
            $this->webhookLogger->warning(
                'Webhook received from disabled (or non-existent) project.',
                [
                    'provider' => $webhook->getProvider(),
                    'repository' => $webhook->getRepository(),
                    'owner' => $webhook->getOwner(),
                    'project' => $project?->getId(),
                    'enabled' => $project?->isEnabled()
                ]
            );

            return null;
        }

        return $project;
    }
}
