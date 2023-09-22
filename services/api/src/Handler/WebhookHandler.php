<?php

namespace App\Handler;

use App\Entity\Project;
use App\Model\Webhook\WebhookInterface;
use App\Repository\ProjectRepository;
use App\Service\Webhook\WebhookProcessor;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class WebhookHandler extends SqsHandler
{
    /**
     * @param SerializerInterface&DenormalizerInterface&NormalizerInterface $serializer
     */
    public function __construct(
        private readonly WebhookProcessor $webhookProcessor,
        private readonly LoggerInterface $webhookLogger,
        private readonly ProjectRepository $projectRepository,
        private readonly SerializerInterface $serializer
    ) {
    }

    public function handleSqs(SqsEvent $event, Context $context): void
    {
        foreach ($event->getRecords() as $record) {
            $webhook = $this->serializer->deserialize(
                $record->getBody(),
                WebhookInterface::class,
                'json'
            );

            $this->processWebhookEvent($webhook);
        }
    }


    /**
     * Process the incoming webhook event payload.
     */
    private function processWebhookEvent(WebhookInterface $webhook): void
    {
        $project = $this->getProject($webhook);

        if (!$project) {
            return;
        }

        $this->webhookProcessor->process($project, $webhook);
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
