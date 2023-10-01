<?php

namespace App\Service\Formatter;

use Packages\Models\Enum\PublishableCheckRunStatus;

class CheckRunFormatterService
{
    public function formatTitle(PublishableCheckRunStatus $status, float $coveragePercentage): string
    {
        return match ($status) {
            default => sprintf('Total Coverage: %s%%', $coveragePercentage),
            PublishableCheckRunStatus::IN_PROGRESS => 'Waiting for any additional coverage uploads...',
        };
    }

    public function formatSummary(): string
    {
        return '';
    }
}
