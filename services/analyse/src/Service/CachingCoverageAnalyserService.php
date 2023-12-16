<?php

namespace App\Service;

use App\Model\Report;
use App\Model\ReportWaypoint;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Service\Carryforward\CachingCarryforwardTagService;
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use App\Service\Diff\CachingDiffParserService;
use App\Service\Diff\DiffParserServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use WeakMap;

/**
 * An in-memory caching implementation for caching queries on reports.
 *
 * This sits on top of the lazy report ({@see Report}) and stops us from
 * repeatedly fetching the same metrics when building parameters for
 * each of the report's metrics.
 */
class CachingCoverageAnalyserService extends CoverageAnalyserService
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
        #[Autowire(service: CachingQueryService::class)]
        QueryServiceInterface $queryService,
        #[Autowire(service: CachingDiffParserService::class)]
        DiffParserServiceInterface $diffParser,
        #[Autowire(service: CachingCarryforwardTagService::class)]
        CarryforwardTagServiceInterface $carryforwardTagService
    ) {
        parent::__construct($queryService, $diffParser, $carryforwardTagService);

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

    protected function getUploads(ReportWaypoint $waypoint): TotalUploadsQueryResult
    {
        if (!isset($this->uploads[$waypoint])) {
            $this->uploads[$waypoint] = parent::getUploads($waypoint);
        }

        return $this->uploads[$waypoint];
    }

    protected function getTotalLines(ReportWaypoint $waypoint): int
    {
        if (!isset($this->totalLines[$waypoint])) {
            $this->totalLines[$waypoint] = parent::getTotalLines($waypoint);
        }

        return $this->totalLines[$waypoint];
    }

    protected function getAtLeastPartiallyCoveredLines(ReportWaypoint $waypoint): int
    {
        if (!isset($this->atLeastPartiallyCoveredLines[$waypoint])) {
            $this->atLeastPartiallyCoveredLines[$waypoint] = parent::getAtLeastPartiallyCoveredLines($waypoint);
        }

        return $this->atLeastPartiallyCoveredLines[$waypoint];
    }

    protected function getUncoveredLines(ReportWaypoint $waypoint): int
    {
        if (!isset($this->uncoveredLines[$waypoint])) {
            $this->uncoveredLines[$waypoint] = parent::getUncoveredLines($waypoint);
        }

        return $this->uncoveredLines[$waypoint];
    }

    protected function getCoveragePercentage(ReportWaypoint $waypoint): float
    {
        if (!isset($this->coveragePercentage[$waypoint])) {
            $this->coveragePercentage[$waypoint] = parent::getCoveragePercentage($waypoint);
        }

        return $this->coveragePercentage[$waypoint];
    }

    protected function getTagCoverage(ReportWaypoint $waypoint): TagCoverageCollectionQueryResult
    {
        if (!isset($this->tagCoverage[$waypoint])) {
            $this->tagCoverage[$waypoint] = parent::getTagCoverage($waypoint);
        }

        return $this->tagCoverage[$waypoint];
    }

    protected function getDiffCoveragePercentage(ReportWaypoint $waypoint): float|null
    {
        if (!isset($this->diffCoveragePercentage[$waypoint])) {
            // Weak maps can't store null values (i.e. the value is never persisted), so
            // we're converting it to false when stored in the map
            $this->diffCoveragePercentage[$waypoint] = parent::getDiffCoveragePercentage($waypoint) ?? false;
        }

        return $this->diffCoveragePercentage[$waypoint] ?: null;
    }

    protected function getLeastCoveredDiffFiles(
        ReportWaypoint $waypoint,
        int $limit = CoverageAnalyserService::DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT
    ): FileCoverageCollectionQueryResult {
        if (!array_key_exists($limit, $this->leastCoveredDiffFiles[$waypoint])) {
            $this->leastCoveredDiffFiles[$waypoint] = array_merge(
                $this->leastCoveredDiffFiles[$waypoint] ?? [],
                [
                    $limit => parent::getLeastCoveredDiffFiles($waypoint, $limit)
                ]
            );
        }

        return $this->leastCoveredDiffFiles[$waypoint][$limit];
    }

    protected function getDiffLineCoverage(ReportWaypoint $waypoint): LineCoverageCollectionQueryResult
    {
        if (!isset($this->diffLineCoverage[$waypoint])) {
            $this->diffLineCoverage[$waypoint] = parent::getDiffLineCoverage($waypoint);
        }

        return $this->diffLineCoverage[$waypoint];
    }
}
