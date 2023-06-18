<?php

namespace App\Model;

use App\Model\QueryResult\MultiFileCoverageQueryResult;
use App\Model\QueryResult\MultiLineCoverageQueryResult;
use App\Model\QueryResult\MultiTagCoverageQueryResult;

interface PublishableCoverageDataInterface
{
    /**
     * Get the total number of uploads for a particular commit.
     */
    public function getTotalUploads(): int;

    /**
     * Get the total number of lines which are coverable across the whole repository.
     */
    public function getTotalLines(): int;

    /**
     * Get the total number of lines which are at least partially covered across the whole repository.
     */
    public function getAtLeastPartiallyCoveredLines(): int;

    /**
     * Get the total number of lines which are not covered across the whole repository.
     */
    public function getUncoveredLines(): int;

    /**
     * Get the total coverage percentage across the whole repository.
     */
    public function getCoveragePercentage(): float;

    /**
     * Get the total coverage percentage the PR diff.
     */
    public function getDiffCoveragePercentage(): float;

    /**
     * Get the coverage percentage for each file in the PR diff, ordered by least covered
     * first.
     */
    public function getLeastCoveredDiffFiles(int $limit): MultiFileCoverageQueryResult;

    /**
     * Get the coverage per line of the PR diff.
     */
    public function getDiffLineCoverage(): MultiLineCoverageQueryResult;

    /**
     * Get the total coverage percentage for each tag.
     */
    public function getTagCoverage(): MultiTagCoverageQueryResult;
}
