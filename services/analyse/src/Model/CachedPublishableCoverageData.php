<?php

namespace App\Model;

use App\Exception\QueryException;
use App\Query\CommitLineCoverageQuery;
use App\Query\TotalCommitCoverageQuery;

/**
 * @psalm-import-type CommitCoverage from TotalCommitCoverageQuery
 * @psalm-import-type CommitLineCoverage from CommitLineCoverageQuery
 */
class CachedPublishableCoverageData extends AbstractPublishableCoverageData
{
    private ?int $totalUploads;

    /**
     * @var CommitCoverage
     */
    private ?array $totalCommitCoverage;

    /**
     * @var CommitLineCoverage[]
     */
    private ?array $commitLineCoverage;

    public function getTotalUploads(): int
    {
        if (is_null($this->totalUploads)) {
            $this->totalUploads = parent::getTotalUploads();
        }

        return $this->totalUploads;
    }

    /**
     * @throws QueryException
     */
    public function getTotalLines(): int
    {
        if (is_null($this->totalCommitCoverage)) {
            $this->totalCommitCoverage = parent::getTotalCommitCoverage();
        }

        return $this->totalCommitCoverage['lines'];
    }

    /**
     * @throws QueryException
     */
    public function getAtLeastPartiallyCoveredLines(): int
    {
        if (is_null($this->totalCommitCoverage)) {
            $this->totalCommitCoverage = parent::getTotalCommitCoverage();
        }

        return $this->totalCommitCoverage['covered'] + $this->totalCommitCoverage['partial'];
    }

    /**
     * @throws QueryException
     */
    public function getUncoveredLines(): int
    {
        if (is_null($this->totalCommitCoverage)) {
            $this->totalCommitCoverage = parent::getTotalCommitCoverage();
        }

        return $this->totalCommitCoverage['uncovered'];
    }

    /**
     * @throws QueryException
     */
    public function getCoveragePercentage(): float
    {
        if (is_null($this->totalCommitCoverage)) {
            $this->totalCommitCoverage = parent::getTotalCommitCoverage();
        }

        return $this->totalCommitCoverage["coveragePercentage"];
    }

    public function getCommitLineCoverage(): array
    {
        if (is_null($this->commitLineCoverage)) {
            $this->commitLineCoverage = parent::getCommitLineCoverage();
        }

        return $this->commitLineCoverage;
    }
}
