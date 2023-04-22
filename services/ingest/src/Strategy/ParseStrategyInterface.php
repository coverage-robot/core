<?php

namespace App\Strategy;

use App\Model\ProjectCoverage;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.parser_strategy')]
interface ParseStrategyInterface
{
    public function supports(string $content): bool;

    public function parse(string $content): ProjectCoverage;
}
