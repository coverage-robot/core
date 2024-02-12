<?php

namespace App\Extension\Function;

use Override;
use Packages\Contracts\Event\BaseAwareEventInterface;

final class EventFunction implements TwigFunctionInterface
{
    use ContextAwareFunctionTrait;

    public function call(array $context): array
    {
        $event = $this->getEventFromContext($context);

        $properties = [
            'head_commit' => $event->getCommit(),
            'event_time' => $event->getEventTime(),
            'pull_request' => $event->getPullRequest()
        ];

        if ($event instanceof BaseAwareEventInterface) {
            return array_merge(
                $properties,
                [
                    'base_ref' => $event->getBaseRef(),
                    'base_commit' => $event->getBaseCommit(),
                ]
            );
        }

        return $properties;
    }

    #[Override]
    public static function getFunctionName(): string
    {
        return 'event';
    }
}
