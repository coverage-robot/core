<?php

namespace App\Service\Publisher;

use App\Exception\PublishException;
use Packages\Message\PublishableMessage\PublishableMessageInterface;
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
}
