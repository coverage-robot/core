<?php

namespace App\Service\Formatter;

use Packages\Message\PublishableMessage\PublishableCheckRunMessage;
use Packages\Message\PublishableMessage\PublishableCheckRunStatus;

final class CheckRunFormatterService
{
    public function formatTitle(PublishableCheckRunMessage $message): string
    {
        return match ($message->getStatus()) {
            default => sprintf(
                'Total Coverage: %s%%%s',
                $message->getCoveragePercentage(),
                $this->getCoverageChange(
                    $message->getBaseCommit(),
                    $message->getCoverageChange()
                )
            ),
            PublishableCheckRunStatus::IN_PROGRESS => 'Waiting for any additional coverage uploads...',
        };
    }

    private function getCoverageChange(?string $baseCommit, ?float $coverageChange): string
    {
        if ($coverageChange === null) {
            return '';
        }

        if ($coverageChange == 0) {
            return sprintf(
                ' (no change compared to %s)',
                $baseCommit ?
                    $this->abbreviateCommit($baseCommit) :
                    'unknown commit'
            );
        }

        return sprintf(
            ' (%s%s%% compared to %s)',
            match (true) {
                $coverageChange > 0 => '+',
                default => '',
            },
            $coverageChange,
            $baseCommit ?
                $this->abbreviateCommit($baseCommit) :
                'unknown commit'
        );
    }

    private function abbreviateCommit(string $commit): string
    {
        return substr($commit, 0, 7);
    }

    public function formatSummary(): string
    {
        return '';
    }
}
