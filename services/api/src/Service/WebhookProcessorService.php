<?php

namespace App\Service;

use App\Entity\Project;
use App\Model\Webhook\WebhookInterface;
use App\Webhook\WebhookProcessorInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class WebhookProcessorService implements WebhookProcessorServiceInterface
{
    /**
     * @param WebhookProcessorInterface[] $webhookProcessors
     */
    public function __construct(
        #[TaggedIterator('app.webhook_processor', defaultIndexMethod: 'getEvent')]
        private readonly iterable $webhookProcessors
    ) {
    }

    public function process(Project $project, WebhookInterface $webhook): void
    {
        $processor = (iterator_to_array($this->webhookProcessors)[$webhook->getEvent()->value]) ?? null;

        if (!$processor instanceof WebhookProcessorInterface) {
            throw new RuntimeException(
                sprintf(
                    'No webhook processor for %s',
                    $webhook->getEvent()->value
                )
            );
        }

        $processor->process($project, $webhook);
    }
}
