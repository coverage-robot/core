<?php

namespace App\Strategy;

use App\Model\ProjectCoverage;

interface ParseStrategyInterface
{
    public function supports(string $content): bool;

    public function parse(string $content): ProjectCoverage;
}
