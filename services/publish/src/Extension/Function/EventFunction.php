<?php

namespace App\Extension\Function;

use Override;
use Packages\Contracts\Event\BaseAwareEventInterface;
use Packages\Contracts\Event\ParentAwareEventInterface;

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
            $properties = array_merge(
                $properties,
                [
                    'base_ref' => $event->getBaseRef(),
                    'base_commit' => $event->getBaseCommit(),
                ]
            );
        }

        if ($event instanceof ParentAwareEventInterface) {
            return array_merge(
                $properties,
                [
                    'parents' => $event->getParent(),
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
