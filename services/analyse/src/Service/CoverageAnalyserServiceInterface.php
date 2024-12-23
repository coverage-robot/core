<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AnalysisException;
use App\Model\CarryforwardTag;
use App\Model\CoverageReportInterface;
use App\Model\ReportWaypoint;
use App\Query\Result\FileCoverageQueryResult;
use App\Query\Result\QueryResultIterator;
use App\Query\Result\TagCoverageQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Provider\Provider;

interface CoverageAnalyserServiceInterface
{
    final public const int DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT = 10;

    /**
     * Get a waypoint for a particular point in time (or, a commit) for a provider.
     */
    public function getWaypoint(
        Provider $provider,
        string $projectId,
        string $owner,
        string $repository,
        string $ref,
        string $commit,
        string|int|null $pullRequest = null
    ): ReportWaypoint;

    /**
     * Get a reporting waypoint for a realtime event which has occured.
     */
    public function getWaypointFromEvent(EventInterface $event): ReportWaypoint;

    /**
     * Build a coverage report for a particular waypoint.
     */
    public function analyse(ReportWaypoint $waypoint): CoverageReportInterface;

    /**
     * Get the uploads which occurred for a particular waypoint.
     */
    public function getUploads(ReportWaypoint $waypoint): TotalUploadsQueryResult;

    /**
     * Get the total number of unique lines recorded across all uploads in the waypoint.
     */
    public function getTotalLines(ReportWaypoint $waypoint): int;

    /**
     * Get the total number of unique lines which had at least 1 line hit in any of the
     * uploads on the waypoint.
     */
    public function getAtLeastPartiallyCoveredLines(ReportWaypoint $waypoint): int;

    /**
     * Get the total number of unique lines which had no line hits in any of the
     * uploads on a waypoint.
     */
    public function getUncoveredLines(ReportWaypoint $waypoint): int;

    /**
     * Get the percentage of lines which are covered in the codebase.
     */
    public function getCoveragePercentage(ReportWaypoint $waypoint): float;

    /**
     * Get the total coverage percentage, split by tag (both tags which had uploads
     * on the waypoint, and those which were carried forward from previous commits)
     *
     * @return QueryResultIterator<TagCoverageQueryResult>
     */
    public function getTagCoverage(ReportWaypoint $waypoint): QueryResultIterator;

    /**
     * Get the line coverage split by file, in any of the uploads or carried forward from
     * previous commits.
     *
     * @return QueryResultIterator<FileCoverageQueryResult>
     */
    public function getFileCoverage(ReportWaypoint $waypoint): QueryResultIterator;

    /**
     * Get the coverage percentage of just the diff associated with the waypoint.
     */
    public function getDiffCoveragePercentage(ReportWaypoint $waypoint): float|null;

    /**
     * Get the total number of lines, which were in the diff of the waypoint, that were hit by at least
     * one upload.
     */
    public function getLeastCoveredDiffFiles(
        ReportWaypoint $waypoint,
        int $limit = self::DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT
    ): QueryResultIterator;

    /**
     * Get the individual lines and their associated coverage for the diff of the waypoint.
     *
     * @return QueryResultIterator<FileCoverageQueryResult>
     */
    public function getDiffLineCoverage(ReportWaypoint $waypoint): QueryResultIterator;

    /**
     * Get all of the tags which had no uploads on a waypoint, and should be carried forward from
     * previous commits.
     *
     * @return CarryforwardTag[]
     */
    public function getCarryforwardTags(ReportWaypoint $waypoint): array;

    /**
     * Get the diff associated with the waypoint.
     *
     * @return array<string, array<int, int>>
     *
     * @throws AnalysisException
     */
    public function getDiff(ReportWaypoint $waypoint): array;

    /**
     * Get the commit history from before the waypoint.
     *
     * @return array{commit: string, merged: bool, ref: string|null}[]
     *
     * @throws AnalysisException
     */
    public function getHistory(ReportWaypoint $waypoint, int $page = 1): array;
}
