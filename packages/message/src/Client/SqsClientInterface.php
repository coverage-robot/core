<?php

namespace Packages\Message\Client;

use Packages\Contracts\PublishableMessage\PublishableMessageInterface;

interface SqsClientInterface
{
    public function dispatch(PublishableMessageInterface $publishableMessage): bool;

    /**
     * Get the full SQS Queue URL for a queue (using its name).
     */
    public function getQueueUrl(string $queueName): ?string;
}
