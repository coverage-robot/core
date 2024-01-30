<?php

namespace App\Service;

use Ramsey\Uuid\Uuid;

final class UniqueIdGeneratorService implements UniqueIdGeneratorServiceInterface
{
    /**
     * Generate a simple Uuid v4 for coverage file identifiers.
     *
     */
    public function generate(): string
    {
        return Uuid::uuid4()->toString();
    }
}
