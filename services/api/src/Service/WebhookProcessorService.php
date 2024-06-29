<?php

namespace App\Service;

use App\Model\Webhook\WebhookInterface;
use App\Webhook\Processor\WebhookProcessorInterface;
use Override;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class WebhookProcessorService implements WebhookProcessorServiceInterface
{
    /**
     * @param WebhookProcessorInterface[] $webhookProcessors
     */
    public function __construct(
        #[AutowireIterator('app.webhook_processor', defaultIndexMethod: 'getEvent')]
        private readonly iterable $webhookProcessors
    ) {
    }

    #[Override]
    public function process(WebhookInterface $webhook): void
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
