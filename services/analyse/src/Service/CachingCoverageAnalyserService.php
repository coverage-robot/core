<?php

namespace App\Service;

use App\Exception\AnalysisException;
use App\Exception\QueryException;
use App\Model\CoverageReport;
use App\Model\CoverageReportInterface;
use App\Model\ReportWaypoint;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use Override;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use WeakMap;

/**
 * An in-memory caching implementation for caching queries on reports.
 *
 * This sits on top of the lazy report ({@see CoverageReport}) and stops us from
 * repeatedly fetching the same metrics when building parameters for
 * each of the report's metrics.
 */
final class CachingCoverageAnalyserService implements CoverageAnalyserServiceInterface
{
    /**
     * @var WeakMap<ReportWaypoint, TotalUploadsQueryResult>
     */
    private WeakMap $uploads;

    /**
     * @var WeakMap<ReportWaypoint, int>
     */
    private WeakMap $totalLines;

    /**
     * @var WeakMap<ReportWaypoint, int>
     */
    private WeakMap $atLeastPartiallyCoveredLines;

    /**
     * @var WeakMap<ReportWaypoint, int>
     */
    private WeakMap $uncoveredLines;

    /**
     * @var WeakMap<ReportWaypoint, float>
     */
    private WeakMap $coveragePercentage;

    /**
     * @var WeakMap<ReportWaypoint, TagCoverageCollectionQueryResult>
     */
    private WeakMap $tagCoverage;

    /**
     * @var WeakMap<ReportWaypoint, float|false>
     */
    private WeakMap $diffCoveragePercentage;

    /**
     * @var WeakMap<ReportWaypoint, LineCoverageCollectionQueryResult>
     */
    private WeakMap $diffLineCoverage;

    /**
     * @var WeakMap<ReportWaypoint, array<int, FileCoverageCollectionQueryResult>>
     */
    private WeakMap $leastCoveredDiffFiles;

    public function __construct(
        #[Autowire(service: CoverageAnalyserService::class)]
        private readonly CoverageAnalyserServiceInterface $coverageAnalyserService,
    ) {
        /**
         * @var WeakMap<ReportWaypoint, TotalUploadsQueryResult> $uploadsCache
         */
        $uploadsCache = new WeakMap();
        $this->uploads = $uploadsCache;
        /**
         * @var WeakMap<ReportWaypoint, int> $totalLinesCache
         */
        $totalLinesCache = new WeakMap();
        $this->totalLines = $totalLinesCache;
        /**
         * @var WeakMap<ReportWaypoint, int> $atLeastPartiallyCoveredLinesCache
         */
        $atLeastPartiallyCoveredLinesCache = new WeakMap();
        $this->atLeastPartiallyCoveredLines = $atLeastPartiallyCoveredLinesCache;
        /**
         * @var WeakMap<ReportWaypoint, int> $uncoveredLinesCache
         */
        $uncoveredLinesCache = new WeakMap();
        $this->uncoveredLines = $uncoveredLinesCache;
        /**
         * @var WeakMap<ReportWaypoint, float> $coveragePercentageCache
         */
        $coveragePercentageCache = new WeakMap();
        $this->coveragePercentage = $coveragePercentageCache;
        /**
         * @var WeakMap<ReportWaypoint, TagCoverageCollectionQueryResult> $tagCoverageCache
         */
        $tagCoverageCache = new WeakMap();
        $this->tagCoverage = $tagCoverageCache;
        /**
         * @var WeakMap<ReportWaypoint, float|false> $diffCoveragePercentageCache
         */
        $diffCoveragePercentageCache = new WeakMap();
        $this->diffCoveragePercentage = $diffCoveragePercentageCache;
        /**
         * @var WeakMap<ReportWaypoint, LineCoverageCollectionQueryResult> $diffLineCoverageCache
         */
        $diffLineCoverageCache = new WeakMap();
        $this->diffLineCoverage = $diffLineCoverageCache;
        /**
         * @var WeakMap<ReportWaypoint, array<int, FileCoverageCollectionQueryResult>> $leastCoveredDiffFilesCache
         */
        $leastCoveredDiffFilesCache = new WeakMap();
        $this->leastCoveredDiffFiles = $leastCoveredDiffFilesCache;
    }


    #[Override]
    public function getWaypoint(
        Provider $provider,
        string $owner,
        string $repository,
        string $ref,
        string $commit,
        int|string|null $pullRequest = null
    ): ReportWaypoint {
        return $this->coverageAnalyserService->getWaypoint(
            $provider,
            $owner,
            $repository,
            $ref,
            $commit,
            $pullRequest
        );
    }

    #[Override]
    public function getWaypointFromEvent(EventInterface $event): ReportWaypoint
    {
        return $this->coverageAnalyserService->getWaypointFromEvent($event);
    }

    #[Override]
    public function analyse(ReportWaypoint $waypoint): CoverageReportInterface
    {
        try {
            return new CoverageReport(
                waypoint: $waypoint,
                uploads:  fn(): TotalUploadsQueryResult => $this->getUploads($waypoint),
                totalLines: fn(): int => $this->getTotalLines($waypoint),
                atLeastPartiallyCoveredLines: fn(): int => $this->getAtLeastPartiallyCoveredLines($waypoint),
                uncoveredLines: fn(): int => $this->getUncoveredLines($waypoint),
                coveragePercentage: fn(): float => $this->getCoveragePercentage($waypoint),
                tagCoverage: fn(): TagCoverageCollectionQueryResult => $this->getTagCoverage($waypoint),
                diffCoveragePercentage: fn(): ?float => $this->getDiffCoveragePercentage($waypoint),
                leastCoveredDiffFiles: fn(): FileCoverageCollectionQueryResult =>
                    $this->getLeastCoveredDiffFiles($waypoint),
                diffLineCoverage: fn(): LineCoverageCollectionQueryResult =>
                    $this->getDiffLineCoverage($waypoint)
            );
        } catch (QueryException $queryException) {
            throw new AnalysisException(
                'Unable to analyse event for report generation.',
                0,
                $queryException
            );
        }
    }

    /**
     * @throws QueryException
     */
    #[Override]
    public function getUploads(ReportWaypoint $waypoint): TotalUploadsQueryResult
    {
        if (!isset($this->uploads[$waypoint])) {
            $this->uploads[$waypoint] = $this->coverageAnalyserService->getUploads($waypoint);
        }

        return $this->uploads[$waypoint];
    }

    #[Override]
    public function getTotalLines(ReportWaypoint $waypoint): int
    {
        if (!isset($this->totalLines[$waypoint])) {
            $this->totalLines[$waypoint] = $this->coverageAnalyserService->getTotalLines($waypoint);
        }

        return $this->totalLines[$waypoint];
    }

    #[Override]
    public function getAtLeastPartiallyCoveredLines(ReportWaypoint $waypoint): int
    {
        if (!isset($this->atLeastPartiallyCoveredLines[$waypoint])) {
            $this->atLeastPartiallyCoveredLines[$waypoint] = $this->coverageAnalyserService
                ->getAtLeastPartiallyCoveredLines($waypoint);
        }

        return $this->atLeastPartiallyCoveredLines[$waypoint];
    }

    #[Override]
    public function getUncoveredLines(ReportWaypoint $waypoint): int
    {
        if (!isset($this->uncoveredLines[$waypoint])) {
            $this->uncoveredLines[$waypoint] = $this->coverageAnalyserService->getUncoveredLines($waypoint);
        }

        return $this->uncoveredLines[$waypoint];
    }

    #[Override]
    public function getCoveragePercentage(ReportWaypoint $waypoint): float
    {
        if (!isset($this->coveragePercentage[$waypoint])) {
            $this->coveragePercentage[$waypoint] = $this->coverageAnalyserService->getCoveragePercentage($waypoint);
        }

        return $this->coveragePercentage[$waypoint];
    }

    #[Override]
    public function getTagCoverage(ReportWaypoint $waypoint): TagCoverageCollectionQueryResult
    {
        if (!isset($this->tagCoverage[$waypoint])) {
            $this->tagCoverage[$waypoint] = $this->coverageAnalyserService->getTagCoverage($waypoint);
        }

        return $this->tagCoverage[$waypoint];
    }

    #[Override]
    public function getDiffCoveragePercentage(ReportWaypoint $waypoint): float|null
    {
        if (!isset($this->diffCoveragePercentage[$waypoint])) {
            // Weak maps can't store null values (i.e. the value is never persisted), so
            // we're converting it to false when stored in the map
            $this->diffCoveragePercentage[$waypoint] = $this->coverageAnalyserService
                ->getDiffCoveragePercentage($waypoint) ?? false;
        }

        $diffCoveragePercentage = $this->diffCoveragePercentage[$waypoint];

        return $diffCoveragePercentage !== false ? $diffCoveragePercentage : null;
    }

    #[Override]
    public function getLeastCoveredDiffFiles(
        ReportWaypoint $waypoint,
        int $limit = CoverageAnalyserService::DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT
    ): FileCoverageCollectionQueryResult {
        if (
            !isset($this->leastCoveredDiffFiles[$waypoint]) ||
            !array_key_exists($limit, $this->leastCoveredDiffFiles[$waypoint])
        ) {
            $this->leastCoveredDiffFiles[$waypoint] = array_replace(
                $this->leastCoveredDiffFiles[$waypoint] ?? [],
                [
                    $limit => $this->coverageAnalyserService->getLeastCoveredDiffFiles($waypoint, $limit)
                ]
            );
        }

        return $this->leastCoveredDiffFiles[$waypoint][$limit];
    }

    #[Override]
    public function getDiffLineCoverage(ReportWaypoint $waypoint): LineCoverageCollectionQueryResult
    {
        if (!isset($this->diffLineCoverage[$waypoint])) {
            $this->diffLineCoverage[$waypoint] = $this->coverageAnalyserService->getDiffLineCoverage($waypoint);
        }

        return $this->diffLineCoverage[$waypoint];
    }

    #[Override]
    public function getDiff(ReportWaypoint $waypoint): array
    {
        return $this->coverageAnalyserService->getDiff($waypoint);
    }

    #[Override]
    public function getHistory(ReportWaypoint $waypoint, int $page = 1): array
    {
        return $this->coverageAnalyserService->getHistory($waypoint, $page);
    }
}
