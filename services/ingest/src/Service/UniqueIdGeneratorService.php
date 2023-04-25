<?php

namespace App\Service;

use Ramsey\Uuid\Uuid;

class UniqueIdGeneratorService
{
    public function generate(): string
    {
        return Uuid::uuid4()->toString();
    }
}
