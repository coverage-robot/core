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

    public function getUploads(): TotalUploadsQueryResult;

    public function getLatestSuccessfulUpload(): ?DateTimeImmutable;

    public function getTotalLines(): int;

    public function getAtLeastPartiallyCoveredLines(): int;

    public function getUncoveredLines(): int;

    public function getCoveragePercentage(): float;

    public function getTagCoverage(): TagCoverageCollectionQueryResult;

    public function getDiffCoveragePercentage(): float|null;

    public function getLeastCoveredDiffFiles(): FileCoverageCollectionQueryResult;

    public function getDiffLineCoverage(): LineCoverageCollectionQueryResult;

    public function getDiff(): array;
}
