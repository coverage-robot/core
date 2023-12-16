<?php

namespace App\Model;

use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use Closure;
use DateTimeImmutable;

/**
 * A coverage report for a particular waypoint.
 *
 * This leverages Closures to lazy load metrics as they are requested.
 */
class Report implements ReportInterface
{
    /**
     * @var Closure():TotalUploadsQueryResult|TotalUploadsQueryResult $uploads
     */
    private Closure|TotalUploadsQueryResult $uploads;

    /**
     * @var Closure():int|int $totalLines
     */
    private Closure|int $totalLines;

    /**
     * @var int|Closure():int $atLeastPartiallyCoveredLines
     */
    private Closure|int $atLeastPartiallyCoveredLines;

    /**
     * @var int|Closure():int $uncoveredLines
     */
    private Closure|int $uncoveredLines;

    /**
     * @var float|Closure():float $coveragePercentage
     */
    private Closure|float $coveragePercentage;

    /**
     * @var TagCoverageCollectionQueryResult|Closure():TagCoverageCollectionQueryResult $tagCoverage
     */
    private Closure|TagCoverageCollectionQueryResult $tagCoverage;

    /**
     * @var (float|null)|Closure():(float|null) $diffCoveragePercentage
     */
    private Closure|float|null $diffCoveragePercentage;

    /**
     * @var FileCoverageCollectionQueryResult|Closure():FileCoverageCollectionQueryResult $leastCoveredDiffFiles
     */
    private Closure|FileCoverageCollectionQueryResult $leastCoveredDiffFiles;

    /**
     * @var LineCoverageCollectionQueryResult|Closure():LineCoverageCollectionQueryResult $diffLineCoverage
     */
    private Closure|LineCoverageCollectionQueryResult $diffLineCoverage;

    /**
     * @var array<string, array<int, int>>|Closure():array<string, array<int, int>> $diff
     */
    private Closure|array $diff;

    /**
     * @param Closure():TotalUploadsQueryResult $uploads
     * @param Closure():int $totalLines
     * @param Closure():int $atLeastPartiallyCoveredLines
     * @param Closure():int $uncoveredLines
     * @param Closure():float $coveragePercentage
     * @param Closure():TagCoverageCollectionQueryResult $tagCoverage
     * @param Closure():(float|null) $diffCoveragePercentage
     * @param Closure():FileCoverageCollectionQueryResult $leastCoveredDiffFiles
     * @param Closure():LineCoverageCollectionQueryResult $diffLineCoverage
     * @param Closure():array<string, array<int, int>> $diff
     */
    public function __construct(
        private readonly ReportWaypoint $waypoint,
        Closure $uploads,
        Closure $totalLines,
        Closure $atLeastPartiallyCoveredLines,
        Closure $uncoveredLines,
        Closure $coveragePercentage,
        Closure $tagCoverage,
        Closure $diffCoveragePercentage,
        Closure $leastCoveredDiffFiles,
        Closure $diffLineCoverage,
        Closure $diff
    ) {
        $this->uploads = $uploads;
        $this->totalLines = $totalLines;
        $this->atLeastPartiallyCoveredLines = $atLeastPartiallyCoveredLines;
        $this->uncoveredLines = $uncoveredLines;
        $this->coveragePercentage = $coveragePercentage;
        $this->tagCoverage = $tagCoverage;
        $this->diffCoveragePercentage = $diffCoveragePercentage;
        $this->leastCoveredDiffFiles = $leastCoveredDiffFiles;
        $this->diffLineCoverage = $diffLineCoverage;
        $this->diff = $diff;
    }

    public function getWaypoint(): ReportWaypoint
    {
        return $this->waypoint;
    }

    public function getUploads(): TotalUploadsQueryResult
    {
        if (is_callable($this->uploads)) {
            $this->uploads = ($this->uploads)();
        }

        return $this->uploads;
    }

    public function getLatestSuccessfulUpload(): ?DateTimeImmutable
    {
        return $this->getUploads()
            ->getLatestSuccessfulUpload();
    }

    public function getTotalLines(): int
    {
        if (is_callable($this->totalLines)) {
            $this->totalLines = ($this->totalLines)();
        }

        return $this->totalLines;
    }

    public function getAtLeastPartiallyCoveredLines(): int
    {
        if (is_callable($this->atLeastPartiallyCoveredLines)) {
            $this->atLeastPartiallyCoveredLines = ($this->atLeastPartiallyCoveredLines)();
        }

        return $this->atLeastPartiallyCoveredLines;
    }

    public function getUncoveredLines(): int
    {
        if (is_callable($this->uncoveredLines)) {
            $this->uncoveredLines = ($this->uncoveredLines)();
        }

        return $this->uncoveredLines;
    }

    public function getCoveragePercentage(): float
    {
        if (is_callable($this->coveragePercentage)) {
            $this->coveragePercentage = ($this->coveragePercentage)();
        }

        return $this->coveragePercentage;
    }

    public function getTagCoverage(): TagCoverageCollectionQueryResult
    {
        if (is_callable($this->tagCoverage)) {
            $this->tagCoverage = ($this->tagCoverage)();
        }

        return $this->tagCoverage;
    }

    public function getDiffCoveragePercentage(): float|null
    {
        if (is_callable($this->diffCoveragePercentage)) {
            $this->diffCoveragePercentage = ($this->diffCoveragePercentage)();
        }

        return $this->diffCoveragePercentage;
    }

    public function getLeastCoveredDiffFiles(): FileCoverageCollectionQueryResult
    {
        if (is_callable($this->leastCoveredDiffFiles)) {
            $this->leastCoveredDiffFiles = ($this->leastCoveredDiffFiles)();
        }

        return $this->leastCoveredDiffFiles;
    }

    public function getDiffLineCoverage(): LineCoverageCollectionQueryResult
    {
        if (is_callable($this->diffLineCoverage)) {
            $this->diffLineCoverage = ($this->diffLineCoverage)();
        }

        return $this->diffLineCoverage;
    }

    public function getDiff(): array
    {
        if (is_callable($this->diff)) {
            $this->diff = ($this->diff)();
        }

        return $this->diff;
    }

    public function __toString()
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
