<?php

namespace App\Model;

use App\Exception\QueryException;
use App\Query\CommitLineCoverageQuery;
use App\Query\TotalCommitCoverageByTagQuery;
use App\Query\TotalCommitCoverageQuery;

/**
 * @psalm-import-type CommitCoverage from TotalCommitCoverageQuery
 * @psalm-import-type CommitLineCoverage from CommitLineCoverageQuery
 * @psalm-import-type CommitTagCoverage from TotalCommitCoverageByTagQuery
 */
class CachedPublishableCoverageData extends AbstractPublishableCoverageData
{
    private ?int $totalUploads = null;

    /**
     * @var CommitCoverage|null
     */
    private ?array $totalCommitCoverage = null;

    /**
     * @var CommitLineCoverage[]|null
     */
    private ?array $commitLineCoverage = null;

    /**
     * @var CommitTagCoverage[]|null
     */
    private array $totalCommitTagCoverage;

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
    public function getTotalCoveragePercentage(): float
    {
        if (is_null($this->totalCommitCoverage)) {
            $this->totalCommitCoverage = parent::getTotalCommitCoverage();
        }

        return $this->totalCommitCoverage['coveragePercentage'];
    }

    public function getCommitLineCoverage(): array
    {
        if (is_null($this->commitLineCoverage)) {
            $this->commitLineCoverage = parent::getCommitLineCoverage();
        }

        return $this->commitLineCoverage;
    }

    public function getTagCoverage(): array
    {
        if (is_null($this->totalCommitTagCoverage)) {
            $this->totalCommitTagCoverage = parent::getTotalCommitTagCoverage();
        }

        return $this->totalCommitTagCoverage;
    }
}
