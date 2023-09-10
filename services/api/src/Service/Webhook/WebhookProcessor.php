<?php

namespace App\Service\Webhook;

use App\Model\Webhook\AbstractWebhook;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class WebhookProcessor
{
    /**
     * @param WebhookProcessorInterface[] $webhookProcessors
     */
    public function __construct(
        #[TaggedIterator('app.webook_processors', defaultPriorityMethod: 'getProcessorEvent')]
        private readonly iterable $webhookProcessors
    ) {
    }

    public function process(AbstractWebhook $webhook): void
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

        $processor->process($webhook);
    }
}
