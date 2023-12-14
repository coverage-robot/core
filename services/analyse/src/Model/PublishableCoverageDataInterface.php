<?php

namespace App\Model;

use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use DateTimeImmutable;

interface PublishableCoverageDataInterface
{
    /**
     * Get the raw list of uploads, regardless of if they were successful,
     * and the last successful upload ingest date.
     */
    public function getUploads(): TotalUploadsQueryResult;

    /**
     * Get the list of successful uploads for a particular commit.
     */
    public function getSuccessfulUploads(): array;

    /**
     * Get the date of the latest **successful** upload for a particular commit.
     */
    public function getLatestSuccessfulUpload(): DateTimeImmutable|null;

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
     *
     * If the return is null, that represents that the diff was not 'coverable', as in,
     * none of the changed lines had tests which reporting >=0 hits on them.
     */
    public function getDiffCoveragePercentage(): float|null;

    /**
     * Get the coverage percentage for each file in the PR diff, ordered by least covered
     * first.
     */
    public function getLeastCoveredDiffFiles(
        int $limit = PublishableCoverageData::DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT
    ): FileCoverageCollectionQueryResult;

    /**
     * Get the coverage per line of the PR diff.
     */
    public function getDiffLineCoverage(): LineCoverageCollectionQueryResult;

    /**
     * Get the diff attached to the event which triggered this coverage analysis.
     *
     * @return array<string, array<int, int>>
     */
    public function getDiff(): array;

    /**
     * Get the total coverage percentage for each tag.
     */
    public function getTagCoverage(): TagCoverageCollectionQueryResult;
}
