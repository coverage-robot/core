<?php

namespace App\Service;

use App\Entity\Project;
use App\Model\Webhook\WebhookInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

interface WebhookProcessorServiceInterface
{
    public function process(Project $project, WebhookInterface $webhook): void;
}
