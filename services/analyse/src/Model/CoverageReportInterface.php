<?php

declare(strict_types=1);

namespace App\Model;

use App\Query\Result\FileCoverageQueryResult;
use App\Query\Result\LineCoverageQueryResult;
use App\Query\Result\QueryResultIterator;
use App\Query\Result\TagCoverageQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use DateTimeImmutable;
use Stringable;

interface CoverageReportInterface extends Stringable
{
    /**
     * The point in time (or, more specifically the git tree) that this report
     * is for.
     */
    public function getWaypoint(): ReportWaypoint;

    /**
     * The size of the coverage report. Specifically, the number of lines of code coverage
     * the report consists of.
     */
    public function getSize(): int;

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
    public function getCoveragePercentage(bool $rounded = true): float;

    /**
     * The line by line coverage split by file, in any of the uploads or carried forward
     * tags.
     *
     * @return QueryResultIterator<FileCoverageQueryResult>
     */
    public function getFileCoverage(): QueryResultIterator;

    /**
     * The list of tags associated to uploads made, and their coverage.
     *
     * @return QueryResultIterator<TagCoverageQueryResult>
     */
    public function getTagCoverage(): QueryResultIterator;

    /**
     * The percentage of lines that were added in the diff, and were at least partially covered by
     * tests in any of the uploads.
     *
     * This is calculated as: `(hits + partials) / (hits + partials + misses)`
     */
    public function getDiffCoveragePercentage(bool $rounded = true): float|null;

    /**
     * The list of the least covered files which were added to by the diff.
     *
     * @return QueryResultIterator<FileCoverageQueryResult>
     */
    public function getLeastCoveredDiffFiles(): QueryResultIterator;

    /**
     * The number of lines that were added in the diff, and were not at least partially covered by
     * tests in any of the uploads.
     */
    public function getDiffUncoveredLines(): int;

    /**
     * The coverage recorded against each line in the diff.
     *
     * This isn't an exhaustive list of the whole diff (i.e. code comments will **not** show up here). THis
     * is just the lines which were added that were coverable by tests (i.e. seen in at least one of the
     * uploads).
     *
     * @return QueryResultIterator<LineCoverageQueryResult>
     */
    public function getDiffLineCoverage(): QueryResultIterator;
}
