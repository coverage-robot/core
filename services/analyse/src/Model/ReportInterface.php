<?php

namespace App\Model;

use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use DateTimeImmutable;
use Stringable;

interface ReportInterface extends Stringable
{
    /**
     * The point in time (or, more specifically the git tree) that this report
     * is for.
     */
    public function getWaypoint(): ReportWaypoint;

    /**
     * The list of uploads that were provided.
     */
    public function getUploads(): TotalUploadsQueryResult;

    /**
     * The date and time of the **latest** successful upload.
     */
    public function getLatestSuccessfulUpload(): ?DateTimeImmutable;

    /**
     * The total number of lines recorded from all uploads.
     */
    public function getTotalLines(): int;

    /**
     * The total number of lines that were (at least partially) covered by tests in
     * any of the uploads.
     */
    public function getAtLeastPartiallyCoveredLines(): int;

    /**
     * The total number of lines that were not covered in any of the uploads.
     */
    public function getUncoveredLines(): int;

    /**
     * The percentage of lines that were at least partially covered by tests in any of the uploads.
     *
     * This is calculated as: `(hits + partials) / (hits + partials + misses)`
     */
    public function getCoveragePercentage(): float;

    /**
     * The list of tags associated to uploads made, and their coverage.
     */
    public function getTagCoverage(): TagCoverageCollectionQueryResult;

    /**
     * The percentage of lines that were added in the diff, and were at least partially covered by
     * tests in any of the uploads.
     *
     * This is calculated as: `(hits + partials) / (hits + partials + misses)`
     */
    public function getDiffCoveragePercentage(): float|null;

    /**
     * The list of the least covered files which were added to by the diff.
     */
    public function getLeastCoveredDiffFiles(): FileCoverageCollectionQueryResult;

    /**
     * The coverage recorded against each line in the diff.
     *
     * This isn't an exhaustive list of the whole diff (i.e. code comments will **not** show up here). THis
     * is just the lines which were added that were coverable by tests (i.e. seen in at least one of the
     * uploads).
     */
    public function getDiffLineCoverage(): LineCoverageCollectionQueryResult;
}
