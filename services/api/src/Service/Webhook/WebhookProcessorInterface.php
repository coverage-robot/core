<?php

namespace App\Service\Webhook;

use App\Entity\Project;
use App\Model\Webhook\WebhookInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.webhook_processor')]
interface WebhookProcessorInterface
{
    /**
     * Process the incoming webhook event payload using the dedicated webhook
     * event processor.
     */
    public function process(Project $project, WebhookInterface $webhook): void;

    /**
     * The webhook event that this processor is responsible for handling.
     */
    public static function getEvent(): string;
}
