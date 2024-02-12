<?php

namespace App\Extension\Function;

use Override;
use Packages\Contracts\PublishableMessage\PublishableMessage;

final class EventFunction implements TwigFunctionInterface
{
    use ContextAwareFunctionTrait;

    public function call(array $context): array
    {
        $event = $this->getEventFromContext($context);
        $message = $this->getMessageFromContext($context);

        return [
            'head_commit' => $event->getCommit(),
            'event_time' => $event->getEventTime(),
            'base_commit' => $message->getBaseCommit() ?? null,
            'pull_request' => $message->getType() === PublishableMessage::PULL_REQUEST ?
                $event->getPullRequest()
                : null
        ];
    }

    #[Override]
    public static function getFunctionName(): string
    {
        return 'event';
    }
}
