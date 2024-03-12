<?php

namespace App\Model;

use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use Closure;
use DateTimeImmutable;
use Override;

/**
 * A coverage report for a particular waypoint.
 *
 * This leverages closures to lazy load metrics as they are requested, but
 * this is entirely opt-in. If the metric has already been calculated, it can
 * be passed in directly.
 */
final class CoverageReport implements CoverageReportInterface
{
    /**
     * @param ReportWaypoint $waypoint
     * @param TotalUploadsQueryResult|Closure():TotalUploadsQueryResult $uploads
     * @param int|Closure():int $totalLines
     * @param int|Closure():int $atLeastPartiallyCoveredLines
     * @param int|Closure():int $uncoveredLines
     * @param float|Closure():float $coveragePercentage
     * @param TagCoverageCollectionQueryResult|Closure():TagCoverageCollectionQueryResult $tagCoverage
     * @param (float|null)|Closure():(float|null) $diffCoveragePercentage
     * @param FileCoverageCollectionQueryResult|Closure():FileCoverageCollectionQueryResult $leastCoveredDiffFiles
     * @param int|Closure():int $diffUncoveredLines
     * @param LineCoverageCollectionQueryResult|Closure():LineCoverageCollectionQueryResult $diffLineCoverage
     */
    public function __construct(
        private readonly ReportWaypoint $waypoint,
        private Closure|TotalUploadsQueryResult $uploads,
        private Closure|int $totalLines,
        private Closure|int $atLeastPartiallyCoveredLines,
        private Closure|int $uncoveredLines,
        private Closure|float $coveragePercentage,
        private Closure|TagCoverageCollectionQueryResult $tagCoverage,
        private Closure|float|null $diffCoveragePercentage,
        private Closure|FileCoverageCollectionQueryResult $leastCoveredDiffFiles,
        private Closure|int $diffUncoveredLines,
        private Closure|LineCoverageCollectionQueryResult $diffLineCoverage,
    ) {
    }

    #[Override]
    public function getWaypoint(): ReportWaypoint
    {
        return $this->waypoint;
    }

    #[Override]
    public function getUploads(): TotalUploadsQueryResult
    {
        if (is_callable($this->uploads)) {
            $this->uploads = ($this->uploads)();
        }

        return $this->uploads;
    }

    #[Override]
    public function getLatestSuccessfulUpload(): ?DateTimeImmutable
    {
        $ingestTimes = $this->getUploads()
            ->getSuccessfulIngestTimes();

        if ($ingestTimes === []) {
            return null;
        }

        /** @var non-empty-array<DateTimeImmutable> $ingestTimes */
        return max($ingestTimes);
    }

    #[Override]
    public function getTotalLines(): int
    {
        if (is_callable($this->totalLines)) {
            $this->totalLines = ($this->totalLines)();
        }

        return $this->totalLines;
    }

    #[Override]
    public function getAtLeastPartiallyCoveredLines(): int
    {
        if (is_callable($this->atLeastPartiallyCoveredLines)) {
            $this->atLeastPartiallyCoveredLines = ($this->atLeastPartiallyCoveredLines)();
        }

        return $this->atLeastPartiallyCoveredLines;
    }

    #[Override]
    public function getUncoveredLines(): int
    {
        if (is_callable($this->uncoveredLines)) {
            $this->uncoveredLines = ($this->uncoveredLines)();
        }

        return $this->uncoveredLines;
    }

    #[Override]
    public function getCoveragePercentage(bool $rounded = true): float
    {
        if (is_callable($this->coveragePercentage)) {
            $this->coveragePercentage = ($this->coveragePercentage)();
        }

        return $rounded ? round($this->coveragePercentage, 2) : $this->coveragePercentage;
    }

    #[Override]
    public function getTagCoverage(): TagCoverageCollectionQueryResult
    {
        if (is_callable($this->tagCoverage)) {
            $this->tagCoverage = ($this->tagCoverage)();
        }

        return $this->tagCoverage;
    }

    #[Override]
    public function getDiffCoveragePercentage(bool $rounded = true): float|null
    {
        if (is_callable($this->diffCoveragePercentage)) {
            $this->diffCoveragePercentage = ($this->diffCoveragePercentage)();
        }

        return $this->diffCoveragePercentage !== null && $rounded ?
            round($this->diffCoveragePercentage, 2) :
            $this->diffCoveragePercentage;
    }

    #[Override]
    public function getLeastCoveredDiffFiles(): FileCoverageCollectionQueryResult
    {
        if (is_callable($this->leastCoveredDiffFiles)) {
            $this->leastCoveredDiffFiles = ($this->leastCoveredDiffFiles)();
        }

        return $this->leastCoveredDiffFiles;
    }

    #[Override]
    public function getDiffUncoveredLines(): int
    {
        if (is_callable($this->diffUncoveredLines)) {
            $this->diffUncoveredLines = ($this->diffUncoveredLines)();
        }

        return $this->diffUncoveredLines;
    }

    #[Override]
    public function getDiffLineCoverage(): LineCoverageCollectionQueryResult
    {
        if (is_callable($this->diffLineCoverage)) {
            $this->diffLineCoverage = ($this->diffLineCoverage)();
        }

        return $this->diffLineCoverage;
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf(
            'Report#%s-%s-%s-%s-%s',
            $this->waypoint->getProvider()->value,
            $this->waypoint->getOwner(),
            $this->waypoint->getRepository(),
            $this->waypoint->getRef(),
            $this->waypoint->getCommit(),
        );
    }
}
