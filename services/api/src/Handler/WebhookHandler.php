<?php

namespace App\Handler;

use App\Client\CognitoClient;
use App\Client\CognitoClientInterface;
use App\Exception\InvalidWebhookException;
use App\Model\Webhook\WebhookInterface;
use App\Service\WebhookProcessorService;
use App\Service\WebhookProcessorServiceInterface;
use App\Service\WebhookValidationService;
use Bref\Context\Context;
use Bref\Event\InvalidLambdaEvent;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;
use Override;
use Packages\Telemetry\Enum\Unit;
use Packages\Telemetry\Service\MetricServiceInterface;
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
        #[Autowire(service: CognitoClient::class)]
        private readonly CognitoClientInterface $cognitoClient,
        private readonly SerializerInterface $serializer,
        private readonly WebhookValidationService $webhookValidationService,
        private readonly MetricServiceInterface $metricService
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
            } catch (ExceptionInterface $e) {
                $this->webhookLogger->error(
                    'Failed to deserialize webhook payload.',
                    [
                        'exception' => $e,
                        'payload' => $sqsRecord->getBody()
                    ]
                );

                $this->metricService->increment(metric: 'InvalidWebhooks');

                continue;
            } catch (InvalidWebhookException $e) {
                $this->webhookLogger->error(
                    'Failed to validate webhook payload.',
                    [
                        'violations' => $e->getViolations(),
                        'payload' => $sqsRecord->getBody()
                    ]
                );

                $this->metricService->increment(metric: 'InvalidWebhooks');

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
        if (
            !$this->cognitoClient->doesProjectExist(
                $webhook->getProvider(),
                $webhook->getOwner(),
                $webhook->getRepository()
            )
        ) {
            $this->metricService->put(
                metric: 'InvalidWebhooks',
                value: 1,
                unit: Unit::COUNT
            );
            return;
        }

        $this->webhookProcessor->process($webhook);

        $this->metricService->put(
            metric: 'ValidWebhooks',
            value: 1,
            unit: Unit::COUNT
        );
    }
}
