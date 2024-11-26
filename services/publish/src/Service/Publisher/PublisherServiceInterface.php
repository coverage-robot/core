<?php

declare(strict_types=1);

namespace App\Service\Publisher;

use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.publisher_service')]
interface PublisherServiceInterface
{
    /**
     * Check if the publisher supports being executed with the given message.
     */
    public function supports(PublishableMessageInterface $publishableMessage): bool;

    /**
     * Execute the implementation for publishing the message.
     */
    public function publish(PublishableMessageInterface $publishableMessage): bool;

    /**
     * Enforce the priority of the publisher to influence the order publishers
     * are executed in.
     *
     * The higher the number, the earlier the publisher is executed.
     */
    public static function getPriority(): int;
}
