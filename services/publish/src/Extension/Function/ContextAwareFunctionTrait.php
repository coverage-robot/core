<?php

declare(strict_types=1);

namespace App\Extension\Function;

use App\Service\Templating\TemplateRenderingService;
use Override;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use RuntimeException;

trait ContextAwareFunctionTrait
{
    /**
     * Helper method to get the message from the context.
     *
     * @see TemplateRenderingService::renderMessageWithTemplate()
     * @param array<string, array<array-key, mixed>> $context
     */
    protected function getMessageFromContext(array $context): PublishableMessageInterface
    {
        $message = $context['message'] ?? null;

        if ($message === null) {
            throw new RuntimeException('No message found in context.');
        }

        if (!$message instanceof PublishableMessageInterface) {
            throw new RuntimeException('Value is not a valid message.');
        }

        return $message;
    }

    /**
     * Helper method to get the message from the context.
     *
     * @see TemplateRenderingService::renderMessageWithTemplate()
     * @param array<string, array<array-key, mixed>> $context
     */
    public function getEventFromContext(array $context): EventInterface
    {
        $event = $context['event'] ?? null;

        if ($event === null) {
            throw new RuntimeException('No event found in context.');
        }

        if (!$event instanceof EventInterface) {
            throw new RuntimeException('Value is not a valid message.');
        }

        return $event;
    }

    /**
     * @return array<string, bool>
     */
    #[Override]
    public static function getOptions(): array
    {
        return ['needs_context' => true];
    }
}
