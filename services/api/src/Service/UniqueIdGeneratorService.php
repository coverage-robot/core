<?php

namespace App\Service;

use Override;
use Symfony\Component\Uid\Uuid;

final class UniqueIdGeneratorService implements UniqueIdGeneratorServiceInterface
{
    /**
     * Generate a simple Uuid v4 for coverage file identifiers.
     *
     */
    #[Override]
    public function generate(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
