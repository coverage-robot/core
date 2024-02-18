<?php

namespace App\Service\Publisher;

use App\Exception\PublishException;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.publisher_service')]
interface PublisherServiceInterface
{
    /**
     * Check if the publisher supports being executed with the given message.
     *
     * @throws PublishException
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
