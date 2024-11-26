<?php

declare(strict_types=1);

namespace App\Service;

interface UniqueIdGeneratorServiceInterface
{
    public function generate(): string;
}
