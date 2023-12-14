<?php

namespace App\Service;

use App\Enum\QueryParameter;
use App\Exception\AnalysisException;
use App\Exception\ComparisonException;
use App\Exception\QueryException;
use App\Model\QueryParameterBag;
use App\Model\Report;
use App\Model\ReportComparison;
use App\Model\ReportInterface;
use App\Model\ReportWaypoint;
use App\Query\FileCoverageQuery;
use App\Query\LineCoverageQuery;
use App\Query\Result\CoverageQueryResult;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalTagCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use App\Service\Diff\DiffParserService;
use App\Service\Diff\DiffParserServiceInterface;
use Packages\Contracts\Event\EventInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CoverageAnalyserService implements CoverageAnalyserServiceInterface
{
    public const DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT = 10;

    public function __construct(
        #[Autowire(service: QueryService::class)]
        private readonly QueryServiceInterface $queryService,
        #[Autowire(service: DiffParserService::class)]
        private readonly DiffParserServiceInterface $diffParser,
        #[Autowire(service: CarryforwardTagService::class)]
        private readonly CarryforwardTagServiceInterface $carryforwardTagService
    ) {
    }

    /**
     * Convert a real-time event into a reporting waypoint that represents a comparable
     * point in time (or, a commit) for a provider.
     */
    public function getWaypointFromEvent(EventInterface $event): ReportWaypoint
    {
        return new ReportWaypoint(
            $event->getProvider(),
            $event->getOwner(),
            $event->getRepository(),
            $event->getRef(),
            $event->getCommit()
        );
    }

    /**
     * Build a full coverage report for a particular waypoint.
     *
     * @throws AnalysisException
     */
    public function analyse(ReportWaypoint $waypoint): ReportInterface
    {
        try {
            return new Report(
                $waypoint,
                $this->getUploads($waypoint),
                $this->getTotalLines($waypoint),
                $this->getAtLeastPartiallyCoveredLines($waypoint),
                $this->getUncoveredLines($waypoint),
                $this->getCoveragePercentage($waypoint),
                $this->getTagCoverage($waypoint),
                $this->getDiffCoveragePercentage($waypoint),
                $this->getLeastCoveredDiffFiles($waypoint),
                $this->getDiffLineCoverage($waypoint),
                $this->getDiff($waypoint)
            );
        } catch (QueryException $e) {
            throw new AnalysisException(
                'Unable to analyse event for report generation.',
                0,
                $e
            );
        }
    }

    /**
     * Compare two (comparable) reports against each other.
     */
    public function compare(ReportInterface $base, ReportInterface $head): ReportComparison
    {
        if (!$base->getWaypoint()->comparable($head->getWaypoint())) {
            throw ComparisonException::notComparable(
                $base->getWaypoint(),
                $head->getWaypoint()
            );
        }

        return new ReportComparison(
            $base,
            $head
        );
    }

    private function getUploads(ReportWaypoint $waypoint): TotalUploadsQueryResult
    {
        /** @var TotalUploadsQueryResult $totalUploads */
        $totalUploads = $this->queryService->runQuery(
            TotalUploadsQuery::class,
            QueryParameterBag::fromWaypoint($waypoint)
        );

        return $totalUploads;
    }

    private function getSuccessfulUploads(ReportWaypoint $waypoint): array
    {
        return $this->getUploads($waypoint)
            ->getSuccessfulUploads();
    }

    private function getTotalLines(ReportWaypoint $waypoint): int
    {
        $params = QueryParameterBag::fromWaypoint($waypoint);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward(
                $waypoint,
                $this->getUploads($waypoint)
                    ->getSuccessfulTags()
            )
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads($waypoint)
        );

        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $totalCoverage->getLines();
    }

    private function getAtLeastPartiallyCoveredLines(ReportWaypoint $waypoint): int
    {
        $params = QueryParameterBag::fromWaypoint($waypoint);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward(
                $waypoint,
                $this->getUploads($waypoint)
                    ->getSuccessfulTags()
            )
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads($waypoint)
        );

        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $totalCoverage->getPartial() + $totalCoverage->getCovered();
    }

    private function getUncoveredLines(ReportWaypoint $waypoint): int
    {
        $params = QueryParameterBag::fromWaypoint($waypoint);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward(
                $waypoint,
                $this->getUploads($waypoint)
                    ->getSuccessfulTags()
            )
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads($waypoint)
        );

        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $totalCoverage->getUncovered();
    }

    private function getCoveragePercentage(ReportWaypoint $waypoint): float
    {
        $params = QueryParameterBag::fromWaypoint($waypoint);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward(
                $waypoint,
                $this->getUploads($waypoint)->getSuccessfulTags()
            )
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads($waypoint)
        );

        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $totalCoverage->getCoveragePercentage();
    }

    private function getTagCoverage(ReportWaypoint $waypoint): TagCoverageCollectionQueryResult
    {
        $params = QueryParameterBag::fromWaypoint($waypoint);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward(
                $waypoint,
                $this->getUploads($waypoint)
                    ->getSuccessfulTags()
            )
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads($waypoint)
        );

        /** @var TagCoverageCollectionQueryResult $tags */
        $tags = $this->queryService->runQuery(TotalTagCoverageQuery::class, $params);

        return $tags;
    }

    /**
     * Get the total coverage percentage of just the lines added to the commits diff.
     *
     * It's important to note that the coverage against a commits diff **does not** make use
     * of tagged coverage which has been carried forward from a previous commit.
     *
     * There's a very particular reason the carried forward coverage isn't factored into the diff
     * coverage. That's because, by definition, coverage which was generated against an older diff
     * cannot be guaranteed to represent the line coverage of the current commit (i.e. added lines
     * will have offset line in the original coverage file, code which has been moved will not be
     * represented in the original coverage file, etc).
     *
     * That's not a problem though, because, by definition, if the coverage has been carried forward
     * its assumed not have changed. And therefore we _should_ be safe under the assumption that whatever
     * coverage has been uploaded currently, is the only coverage which will impact the diff thats
     * currently being analysed.
     *
     * @throws QueryException
     */
    private function getDiffCoveragePercentage(ReportWaypoint $waypoint): float|null
    {
        $diff = $this->getDiff($waypoint);

        if ($diff == []) {
            return 0;
        }

        $params = QueryParameterBag::fromWaypoint($waypoint);
        $params->set(
            QueryParameter::LINE_SCOPE,
            $diff
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads($waypoint)
        );

        /**
         * @var CoverageQueryResult $diffCoverage
         */
        $diffCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        if ($diffCoverage->getLines() === 0) {
            // Theres no 'coverable' lines in the diff (i.e. lines which could've been run by any of the
            // tests), so therefore theres no diff coverage. By default, the coverage percentage will show as
            // 0%, which would be a bit misleading, given when none of the diff was testable in the first place.
            return null;
        }

        return $diffCoverage->getCoveragePercentage();
    }

    /**
     * Get the files which have the least coverage against a commits diff.
     *
     * It's important to note that the coverage against a commits diff **does not** make use
     * of tagged coverage which has been carried forward from a previous commit.
     *
     * There's a very particular reason the carried forward coverage isn't factored into the diff
     * coverage. That's because, by definition, coverage which was generated against an older diff
     * cannot be guaranteed to represent the line coverage of the current commit (i.e. added lines
     * will have offset line in the original coverage file, code which has been moved will not be
     * represented in the original coverage file, etc).
     *
     * That's not a problem though, because, by definition, if the coverage has been carried forward
     * its assumed not have changed. And therefore we _should_ be safe under the assumption that whatever
     * coverage has been uploaded currently, is the only coverage which will impact the diff thats
     * currently being analysed.
     *
     * @throws QueryException
     */
    private function getLeastCoveredDiffFiles(
        ReportWaypoint $waypoint,
        int $limit = self::DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT
    ): FileCoverageCollectionQueryResult {
        $diff = $this->getDiff($waypoint);

        if ($diff == []) {
            return new FileCoverageCollectionQueryResult([]);
        }

        $params = QueryParameterBag::fromWaypoint($waypoint);
        $params->set(
            QueryParameter::LINE_SCOPE,
            $diff
        );
        $params->set(
            QueryParameter::LIMIT,
            $limit
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads($waypoint)
        );

        /**
         * @var FileCoverageCollectionQueryResult $files
         */
        $files = $this->queryService->runQuery(FileCoverageQuery::class, $params);

        return $files;
    }

    /**
     * Get the line coverage of a commits diff.
     *
     * It's important to note that the line coverage against a commits diff **does not** make use
     * of tagged coverage which has been carried forward from a previous commit.
     *
     * There's a very particular reason the carried forward coverage isn't factored into the diff
     * coverage. That's because, by definition, coverage which was generated against an older diff
     * cannot be guaranteed to represent the line coverage of the current commit (i.e. added lines
     * will have offset line in the original coverage file, code which has been moved will not be
     * represented in the original coverage file, etc).
     *
     * That's not a problem though, because, by definition, if the coverage has been carried forward
     * its assumed not have changed. And therefore we _should_ be safe under the assumption that whatever
     * coverage has been uploaded currently, is the only coverage which will impact the diff thats
     * currently being analysed.
     *
     * @throws QueryException
     */
    private function getDiffLineCoverage(ReportWaypoint $waypoint): LineCoverageCollectionQueryResult
    {
        $diff = $this->getDiff($waypoint);

        if ($diff == []) {
            return new LineCoverageCollectionQueryResult([]);
        }

        $params = QueryParameterBag::fromWaypoint($waypoint);
        $params->set(
            QueryParameter::LINE_SCOPE,
            $diff
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads($waypoint)
        );

        /**
         * @var LineCoverageCollectionQueryResult $lines
         */
        $lines = $this->queryService->runQuery(LineCoverageQuery::class, $params);

        return $lines;
    }

    private function getDiff(ReportWaypoint $waypoint): array
    {
        return $this->diffParser->get($waypoint);
    }
}
