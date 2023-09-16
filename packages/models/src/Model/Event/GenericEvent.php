<?php

namespace Packages\Models\Model\Event;

use InvalidArgumentException;

abstract class GenericEvent implements EventInterface
{
    public static function from(array $data): EventInterface
    {
        return match ($data['eventType']) {
            Upload::class => Upload::from($data),
            PipelineComplete::class => PipelineComplete::from($data),
            default => throw new InvalidArgumentException('Invalid event type provided.')
        };
    }

    public function jsonSerialize(): array
    {
        return [
            'eventType' => $this::class
        ];
    }
}
