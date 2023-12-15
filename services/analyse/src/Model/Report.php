<?php

namespace App\Model;

use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use DateTimeImmutable;

class Report implements ReportInterface
{
    /**
     * @param array<string, array<int, int>> $diff
     */
    public function __construct(
        private readonly ReportWaypoint $waypoint,
        private readonly TotalUploadsQueryResult $uploads,
        private readonly int $totalLines,
        private readonly int $atLeastPartiallyCoveredLines,
        private readonly int $uncoveredLines,
        private readonly float $coveragePercentage,
        private readonly TagCoverageCollectionQueryResult $tagCoverage,
        private readonly float|null $diffCoveragePercentage,
        private readonly FileCoverageCollectionQueryResult $leastCoveredDiffFiles,
        private readonly LineCoverageCollectionQueryResult $diffLineCoverage,
        private readonly array $diff
    ) {
    }

    public function getWaypoint(): ReportWaypoint
    {
        return $this->waypoint;
    }

    public function getUploads(): TotalUploadsQueryResult
    {
        return $this->uploads;
    }

    public function getLatestSuccessfulUpload(): ?DateTimeImmutable
    {
        return $this->uploads->getLatestSuccessfulUpload();
    }

    public function getTotalLines(): int
    {
        return $this->totalLines;
    }

    public function getAtLeastPartiallyCoveredLines(): int
    {
        return $this->atLeastPartiallyCoveredLines;
    }

    public function getUncoveredLines(): int
    {
        return $this->uncoveredLines;
    }

    public function getCoveragePercentage(): float
    {
        return $this->coveragePercentage;
    }

    public function getTagCoverage(): TagCoverageCollectionQueryResult
    {
        return $this->tagCoverage;
    }

    public function getDiffCoveragePercentage(): float|null
    {
        return $this->diffCoveragePercentage;
    }

    public function getLeastCoveredDiffFiles(): FileCoverageCollectionQueryResult
    {
        return $this->leastCoveredDiffFiles;
    }

    public function getDiffLineCoverage(): LineCoverageCollectionQueryResult
    {
        return $this->diffLineCoverage;
    }

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

    public function getDiff(): array
    {
        return $this->diff;
    }
}
