<?php

namespace App\Model\Event;

use JsonSerializable;

class IngestCompleteEvent implements JsonSerializable
{
    public function __construct(private readonly string $uniqueId)
    {
    }

    public function jsonSerialize(): array
    {
        return [
            "uniqueId" => $this->uniqueId
        ];
    }
}
