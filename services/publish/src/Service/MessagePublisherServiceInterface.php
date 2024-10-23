<?php

declare(strict_types=1);

namespace App\Service;

use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Telemetry\Enum\Unit;
use Packages\Telemetry\Service\MetricService;
use Packages\Telemetry\Service\MetricServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

interface MessagePublisherServiceInterface
{
    /**
     * Publish the message with _all_ publishers which support it.
     */
    public function publish(PublishableMessageInterface $publishableMessage): bool;
}
