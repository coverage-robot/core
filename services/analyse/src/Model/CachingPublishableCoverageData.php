<?php

namespace App\Model;

use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;

class CachingPublishableCoverageData extends PublishableCoverageData
{
    private ?int $totalUploads = null;

    private ?int $totalLines = null;

    private ?int $atLeastPartiallyCoveredLines = null;

    private ?int $uncoveredLines = null;

    private ?float $coveragePercentage = null;

    private ?TagCoverageCollectionQueryResult $tagCoverage = null;

    private ?float $diffCoveragePercentage = null;

    private ?LineCoverageCollectionQueryResult $diffLineCoverage = null;

    /**
     * @var array<int, FileCoverageCollectionQueryResult>
     */
    private array $leastCoveredDiffFiles = [];

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

    public function getTagCoverage(): TagCoverageCollectionQueryResult
    {
        if (!$this->tagCoverage) {
            $this->tagCoverage = parent::getTagCoverage();
        }

        return $this->tagCoverage;
    }

    public function getDiffCoveragePercentage(): float
    {
        if (!$this->diffCoveragePercentage) {
            $this->diffCoveragePercentage = parent::getDiffCoveragePercentage();
        }

        return $this->diffCoveragePercentage;
    }

    public function getLeastCoveredDiffFiles(
        int $limit = PublishableCoverageData::DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT
    ): FileCoverageCollectionQueryResult {
        if (!array_key_exists($limit, $this->leastCoveredDiffFiles)) {
            $this->leastCoveredDiffFiles[$limit] = parent::getLeastCoveredDiffFiles($limit);
        }

        return $this->leastCoveredDiffFiles[$limit];
    }

    public function getDiffLineCoverage(): LineCoverageCollectionQueryResult
    {
        if (!$this->diffLineCoverage) {
            $this->diffLineCoverage = parent::getDiffLineCoverage();
        }

        return $this->diffLineCoverage;
    }
}
