<?php

namespace App\Model;

use App\Model\QueryResult\TotalLineCoverageQueryResult;
use App\Model\QueryResult\TotalTagCoverageQueryResult;

class CachedPublishableCoverageData extends PublishableCoverageData
{
    private ?int $totalUploads = null;

    private ?int $totalLines = null;

    private ?int $atLeastPartiallyCoveredLines = null;

    private ?int $uncoveredLines = null;

    private ?float $coveragePercentage = null;

    private ?TotalTagCoverageQueryResult $tagCoverage = null;

    private ?TotalLineCoverageQueryResult $lineCoverage = null;

    public function getTotalUploads(): int
    {
        if (!$this->totalUploads) {
            $this->totalUploads = parent::getTotalUploads();
        }

        return $this->totalUploads;
    }

    public function getTotalLines(): int
    {
        if (!$this->totalLines) {
            $this->totalLines = parent::getTotalLines();
        }

        return $this->totalLines;
    }

    public function getAtLeastPartiallyCoveredLines(): int
    {
        if (!$this->atLeastPartiallyCoveredLines) {
            $this->atLeastPartiallyCoveredLines = parent::getAtLeastPartiallyCoveredLines();
        }

        return $this->atLeastPartiallyCoveredLines;
    }

    public function getUncoveredLines(): int
    {
        if (!$this->uncoveredLines) {
            $this->uncoveredLines = parent::getUncoveredLines();
        }

        return $this->uncoveredLines;
    }

    public function getCoveragePercentage(): float
    {
        if (!$this->coveragePercentage) {
            $this->coveragePercentage = parent::getCoveragePercentage();
        }

        return $this->coveragePercentage;
    }

    public function getTagCoverage(): TotalTagCoverageQueryResult
    {
        if (!$this->tagCoverage) {
            $this->tagCoverage = parent::getTagCoverage();
        }

        return $this->tagCoverage;
    }

    public function getLineCoverage(): TotalLineCoverageQueryResult
    {
        if (!$this->lineCoverage) {
            $this->lineCoverage = parent::getLineCoverage();
        }

        return $this->lineCoverage;
    }
}
