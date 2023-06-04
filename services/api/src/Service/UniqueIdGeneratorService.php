<?php

namespace App\Service;

use Ramsey\Uuid\Uuid;

class UniqueIdGeneratorService
{
    /**
     * Generate a simple Uuid v4 for coverage file identifiers.
     *
     * @return string
     */
    public function generate(): string
    {
        return Uuid::uuid4()->toString();
    }
}
