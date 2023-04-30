<?php

namespace App\Model\Event;

class IngestCompleteEvent
{
    private string $uniqueId;

    public function __construct(array $data)
    {
        $this->uniqueId = (string)$data['uniqueId'];
    }

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }
}
