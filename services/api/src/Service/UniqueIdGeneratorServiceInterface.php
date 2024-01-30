<?php

namespace App\Service;

interface UniqueIdGeneratorServiceInterface
{
    public function generate(): string;
}
