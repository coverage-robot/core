<?php

namespace App\Model;

class CachedPublishableCoverageData extends AbstractPublishableCoverageData
{
    private array $totalCommitCoverage;

    private array $commitLineCoverage;

    public function getTotalLines(): int
    {
        if (!isset($this->totalCommitCoverage)) {
            $this->totalCommitCoverage = parent::getTotalCommitCoverage();
        }

        return $this->totalCommitCoverage["lines"];
    }

    public function getAtLeastPartiallyCoveredLines(): int
    {
        if (!isset($this->totalCommitCoverage)) {
            $this->totalCommitCoverage = parent::getTotalCommitCoverage();
        }

        return $this->totalCommitCoverage["covered"] + $this->totalCommitCoverage["partial"];
    }

    public function getUncoveredLines(): int
    {
        if (!isset($this->totalCommitCoverage)) {
            $this->totalCommitCoverage = parent::getTotalCommitCoverage();
        }

        return $this->totalCommitCoverage["uncovered"];
    }

    public function getCoveragePercentage(): float
    {
        if (!isset($this->totalCommitCoverage)) {
            $this->totalCommitCoverage = parent::getTotalCommitCoverage();
        }

        return $this->totalCommitCoverage["coveragePercent"];
    }

    public function getCommitLineCoverage(): array
    {
        if (!isset($this->commitLineCoverage)) {
            $this->commitLineCoverage = parent::getCommitLineCoverage();
        }

        return $this->commitLineCoverage;
    }
}
