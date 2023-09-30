<?php

namespace App\Service\Formatter;

class CheckRunFormatterService
{
    public function formatTitle(float $coveragePercentage): string
    {
        return sprintf(
            'Total Coverage: %s%%',
            $coveragePercentage
        );
    }

    public function formatSummary(): string
    {
        return '';
    }
}
