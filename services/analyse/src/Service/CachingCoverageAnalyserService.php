<?php

namespace App\Service;

use App\Exception\QueryException;
use App\Model\CoverageReport;
use App\Model\ReportWaypoint;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Service\Carryforward\CachingCarryforwardTagService;
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use App\Service\Diff\CachingDiffParserService;
use App\Service\Diff\DiffParserServiceInterface;
use App\Service\History\CachingCommitHistoryService;
use App\Service\History\CommitHistoryServiceInterface;
use App\Trait\InMemoryCacheTrait;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * An in-memory caching implementation for caching queries on reports.
 *
 * This sits on top of the lazy report ({@see CoverageReport}) and stops us from
 * repeatedly fetching the same metrics when building parameters for
 * each of the report's metrics.
 */
final class CachingCoverageAnalyserService extends AbstractCoverageAnalyserService
{
    use InMemoryCacheTrait;

    public function __construct(
        #[Autowire(service: CachingQueryService::class)]
        QueryServiceInterface $queryService,
        #[Autowire(service: CachingDiffParserService::class)]
        DiffParserServiceInterface $diffParser,
        #[Autowire(service: CachingCommitHistoryService::class)]
        CommitHistoryServiceInterface $commitHistoryService,
        #[Autowire(service: CachingCarryforwardTagService::class)]
        CarryforwardTagServiceInterface $carryforwardTagService
    ) {
        parent::__construct(
            $queryService,
            $diffParser,
            $commitHistoryService,
            $carryforwardTagService
        );
    }

    /**
     * @throws QueryException
     */
    #[Override]
    public function getUploads(ReportWaypoint $waypoint): TotalUploadsQueryResult
    {
        if ($this->hasCacheValue(__FUNCTION__, $waypoint)) {
            /**
             * @var TotalUploadsQueryResult
             */
            return $this->getCacheValue(__FUNCTION__, $waypoint);
        }

        $uploads = parent::getUploads($waypoint);
        $this->setCacheValue(__FUNCTION__, $waypoint, $uploads);

        return $uploads;
    }

    #[Override]
    public function getTotalLines(ReportWaypoint $waypoint): int
    {
        if ($this->hasCacheValue(__FUNCTION__, $waypoint)) {
            /**
             * @var int
             */
            return $this->getCacheValue(__FUNCTION__, $waypoint);
        }

        $totalLines = parent::getTotalLines($waypoint);
        $this->setCacheValue(__FUNCTION__, $waypoint, $totalLines);

        return $totalLines;
    }

    #[Override]
    public function getAtLeastPartiallyCoveredLines(ReportWaypoint $waypoint): int
    {
        if ($this->hasCacheValue(__FUNCTION__, $waypoint)) {
            /**
             * @var int
             */
            return $this->getCacheValue(__FUNCTION__, $waypoint);
        }

        $atLeastPartiallyCoveredLines = parent::getAtLeastPartiallyCoveredLines($waypoint);
        $this->setCacheValue(__FUNCTION__, $waypoint, $atLeastPartiallyCoveredLines);

        return $atLeastPartiallyCoveredLines;
    }

    #[Override]
    public function getUncoveredLines(ReportWaypoint $waypoint): int
    {
        if ($this->hasCacheValue(__FUNCTION__, $waypoint)) {
            /**
             * @var int
             */
            return $this->getCacheValue(__FUNCTION__, $waypoint);
        }

        $uncoveredLines = parent::getUncoveredLines($waypoint);
        $this->setCacheValue(__FUNCTION__, $waypoint, $uncoveredLines);

        return $uncoveredLines;
    }

    #[Override]
    public function getCoveragePercentage(ReportWaypoint $waypoint): float
    {
        if ($this->hasCacheValue(__FUNCTION__, $waypoint)) {
            /**
             * @var float
             */
            return $this->getCacheValue(__FUNCTION__, $waypoint);
        }

        $coveragePercentage = parent::getCoveragePercentage($waypoint);
        $this->setCacheValue(__FUNCTION__, $waypoint, $coveragePercentage);

        return $coveragePercentage;
    }

    #[Override]
    public function getTagCoverage(ReportWaypoint $waypoint): TagCoverageCollectionQueryResult
    {
        if ($this->hasCacheValue(__FUNCTION__, $waypoint)) {
            /**
             * @var TagCoverageCollectionQueryResult
             */
            return $this->getCacheValue(__FUNCTION__, $waypoint);
        }

        $tagCoverage = parent::getTagCoverage($waypoint);
        $this->setCacheValue(__FUNCTION__, $waypoint, $tagCoverage);

        return $tagCoverage;
    }

    #[Override]
    public function getDiffCoveragePercentage(ReportWaypoint $waypoint): float|null
    {
        if ($this->hasCacheValue(__FUNCTION__, $waypoint)) {
            /**
             * @var float|null
             */
            return $this->getCacheValue(__FUNCTION__, $waypoint);
        }

        $diffCoveragePercentage = parent::getDiffCoveragePercentage($waypoint);
        $this->setCacheValue(__FUNCTION__, $waypoint, $diffCoveragePercentage);

        return $diffCoveragePercentage;
    }

    #[Override]
    public function getLeastCoveredDiffFiles(
        ReportWaypoint $waypoint,
        int $limit = AbstractCoverageAnalyserService::DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT
    ): FileCoverageCollectionQueryResult {
        $cachedResults = [];
        if ($this->hasCacheValue(__FUNCTION__, $waypoint)) {
            /**
             * @var array<int, FileCoverageCollectionQueryResult> $cachedResults
             */
            $cachedResults = $this->getCacheValue(__FUNCTION__, $waypoint);

            if (array_key_exists($limit, $cachedResults)) {
                return $cachedResults[$limit];
            }
        }

        $leastCoveredDiffFiles = parent::getLeastCoveredDiffFiles($waypoint, $limit);
        $this->setCacheValue(
            __FUNCTION__,
            $waypoint,
            array_replace(
                $cachedResults,
                [
                    $limit => $leastCoveredDiffFiles
                ]
            )
        );

        return $leastCoveredDiffFiles;
    }

    #[Override]
    public function getDiffUncoveredLines(ReportWaypoint $waypoint): int
    {
        if ($this->hasCacheValue(__FUNCTION__, $waypoint)) {
            /**
             * @var int
             */
            return $this->getCacheValue(__FUNCTION__, $waypoint);
        }

        $diffUncoveredLines = parent::getDiffUncoveredLines($waypoint);
        $this->setCacheValue(__FUNCTION__, $waypoint, $diffUncoveredLines);

        return $diffUncoveredLines;
    }

    #[Override]
    public function getDiffLineCoverage(ReportWaypoint $waypoint): LineCoverageCollectionQueryResult
    {
        if ($this->hasCacheValue(__FUNCTION__, $waypoint)) {
            /**
             * @var LineCoverageCollectionQueryResult
             */
            return $this->getCacheValue(__FUNCTION__, $waypoint);
        }

        $diffLineCoverage = parent::getDiffLineCoverage($waypoint);
        $this->setCacheValue(__FUNCTION__, $waypoint, $diffLineCoverage);

        return $diffLineCoverage;
    }
}
