<?php

declare(strict_types=1);

namespace App\Service;

use Packages\Contracts\PublishableMessage\PublishableMessageInterface;

interface MessagePublisherServiceInterface
{
    /**
     * Publish the message with _all_ publishers which support it.
     */
    public function publish(PublishableMessageInterface $publishableMessage): bool;
}
