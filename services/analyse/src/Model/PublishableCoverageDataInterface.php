<?php

namespace App\Model;

use App\Query\CommitLineCoverageQuery;
use App\Query\TotalCommitCoverageByTagQuery;

/**
 * @psalm-import-type CommitLineCoverage from CommitLineCoverageQuery
 * @psalm-import-type CommitTagCoverage from TotalCommitCoverageByTagQuery
 */
interface PublishableCoverageDataInterface
{
    public function getTotalUploads(): int;

    public function getTotalLines(): int;

    public function getAtLeastPartiallyCoveredLines(): int;

    public function getUncoveredLines(): int;

    public function getTotalCoveragePercentage(): float;

    /**
     * @return CommitLineCoverage[]
     */
    public function getCommitLineCoverage(): array;

    /**
     * @return CommitTagCoverage[]
     */
    public function getTagCoverage(): array;
}
