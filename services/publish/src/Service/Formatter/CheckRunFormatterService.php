<?php

namespace App\Service\Formatter;

use Packages\Message\PublishableMessage\PublishableCheckRunStatus;

class CheckRunFormatterService
{
    public function formatTitle(
        PublishableCheckRunStatus $status,
        float $coveragePercentage,
        ?float $coverageChange
    ): string {
        return match ($status) {
            default => sprintf(
                'Total Coverage: %s%%%s',
                $coveragePercentage,
                $this->getCoverageChange($coverageChange)
            ),
            PublishableCheckRunStatus::IN_PROGRESS => 'Waiting for any additional coverage uploads...',
        };
    }

    private function getCoverageChange(?float $coverageChange): string
    {
        if ($coverageChange === null) {
            return '';
        }

        if ($coverageChange == 0) {
            return ' (no change)';
        }

        $sign = match (true) {
            $coverageChange > 0 => '+',
            default => '',
        };

        return sprintf(
            ' (%s%s%%)',
            $sign,
            $coverageChange
        );
    }

    public function formatSummary(): string
    {
        return '';
    }
}
