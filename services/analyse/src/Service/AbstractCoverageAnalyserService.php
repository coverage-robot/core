<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\QueryParameter;
use App\Exception\AnalysisException;
use App\Exception\CommitDiffException;
use App\Exception\CommitHistoryException;
use App\Exception\QueryException;
use App\Model\CarryforwardTag;
use App\Model\CoverageReport;
use App\Model\CoverageReportInterface;
use App\Model\QueryParameterBag;
use App\Model\ReportWaypoint;
use App\Query\FileCoverageQuery;
use App\Query\LineCoverageQuery;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\Result\TotalCoverageQueryResult;
use App\Query\Result\TotalUploadsQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalTagCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use App\Service\Diff\DiffParserServiceInterface;
use App\Service\History\CommitHistoryServiceInterface;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;

abstract class AbstractCoverageAnalyserService implements CoverageAnalyserServiceInterface
{
    public function __construct(
        protected readonly QueryServiceInterface $queryService,
        protected readonly DiffParserServiceInterface $diffParser,
        protected readonly CommitHistoryServiceInterface $commitHistoryService,
        protected readonly CarryforwardTagServiceInterface $carryforwardTagService
    ) {
    }

    /**
     * Get a waypoint for a particular point in time (or, a commit) for a provider.
     */
    #[Override]
    public function getWaypoint(
        Provider $provider,
        string $projectId,
        string $owner,
        string $repository,
        string $ref,
        string $commit,
        string|int|null $pullRequest = null
    ): ReportWaypoint {
        return new ReportWaypoint(
            provider: $provider,
            projectId: $projectId,
            owner: $owner,
            repository: $repository,
            ref: $ref,
            commit: $commit,
            history: fn(ReportWaypoint $waypoint, int $page): array => $this->getHistory($waypoint, $page),
            diff: fn(ReportWaypoint $waypoint): array => $this->getDiff($waypoint),
            pullRequest: $pullRequest
        );
    }

    /**
     * Convert a real-time event into a reporting waypoint that represents a comparable
     * point in time (or, a commit) for a provider.
     */
    #[Override]
    public function getWaypointFromEvent(EventInterface $event): ReportWaypoint
    {
        return $this->getWaypoint(
            $event->getProvider(),
            $event->getProjectId(),
            $event->getOwner(),
            $event->getRepository(),
            $event->getRef(),
            $event->getCommit(),
            $event->getPullRequest()
        );
    }

    /**
     * Build a full coverage report for a particular waypoint.
     *
     * @throws AnalysisException
     */
    #[Override]
    public function analyse(ReportWaypoint $waypoint): CoverageReportInterface
    {
        try {
            return new CoverageReport(
                waypoint: $waypoint,
                size: fn(): int => $this->getReportSize($waypoint),
                uploads:  fn(): TotalUploadsQueryResult => $this->getUploads($waypoint),
                totalLines: fn(): int => $this->getTotalLines($waypoint),
                atLeastPartiallyCoveredLines: fn(): int => $this->getAtLeastPartiallyCoveredLines($waypoint),
                uncoveredLines: fn(): int => $this->getUncoveredLines($waypoint),
                coveragePercentage: fn(): float => $this->getCoveragePercentage($waypoint),
                tagCoverage: fn(): TagCoverageCollectionQueryResult => $this->getTagCoverage($waypoint),
                diffCoveragePercentage: fn(): ?float => $this->getDiffCoveragePercentage($waypoint),
                leastCoveredDiffFiles: fn(): FileCoverageCollectionQueryResult =>
                    $this->getLeastCoveredDiffFiles($waypoint),
                diffUncoveredLines: fn(): int => $this->getDiffUncoveredLines($waypoint),
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
        /** @var TotalUploadsQueryResult $totalUploads */
        $totalUploads = $this->queryService->runQuery(
            TotalUploadsQuery::class,
            QueryParameterBag::fromWaypoint($waypoint)
        );

        return $totalUploads;
    }

    /**
     * Get the total size of the report (aka the total number of lines uploaded to each report)
     *
     * This uses the sum of the total number of lines of the uploads used to generate the report, and
     * allows us to accurately track the total amount of data analysed to build the final report result.
     *
     * The coverage report's 'total number of lines' isn't necessarily comparable, as that represents
     * the unique number of lines of a codebase - i.e. two uploads with coverage for the same line will
     * only return 1 line on the coverage report which vastly under values the effort used to compile results.
     */
    public function getReportSize(ReportWaypoint $waypoint): int
    {
        return array_reduce(
            [
                ...$this->getUploads($waypoint)->getSuccessfulTags(),

                // Include the carried forward tags from the report, so that previous coverage is still
                // factored into the report size analysis
                ...$this->getCarryforwardTags($waypoint)
            ],
            static fn(int $total, Tag $tag): int => $total + array_sum($tag->getSuccessfullyUploadedLines()),
            0
        );
    }

    /**
     * @throws QueryException
     */
    private function getSuccessfulUploads(ReportWaypoint $waypoint): array
    {
        return $this->getUploads($waypoint)
            ->getSuccessfulUploads();
    }

    /**
     * @return DateTimeImmutable[]
     *
     * @throws QueryException
     */
    private function getSuccessfulIngestTimes(ReportWaypoint $waypoint): array
    {
        return $this->getUploads($waypoint)
            ->getSuccessfulIngestTimes();
    }

    /**
     * @throws QueryException
     */
    #[Override]
    public function getTotalLines(ReportWaypoint $waypoint): int
    {
        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);
        $carryforwardTags = $this->getCarryforwardTags($waypoint);

        if (
            $ingestTimes === [] &&
            $uploads === [] &&
            $carryforwardTags === []
        ) {
            // Theres no point in checking coverage as theres no files to check against
            return 0;
        }

        $params = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                $carryforwardTags
            )
            ->set(
                QueryParameter::INGEST_PARTITIONS,
                [
                    ...$ingestTimes,
                    ...array_reduce(
                        $carryforwardTags,
                        static fn(array $ingestTimes, CarryforwardTag $carryforwardTag): array => [
                            ...$ingestTimes,
                            ...$carryforwardTag->getIngestTimes()
                        ],
                        []
                    )
                ]
            )
            ->set(
                QueryParameter::UPLOADS,
                $uploads
            );

        /** @var TotalCoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $totalCoverage->getLines();
    }

    /**
     * @throws QueryException
     */
    #[Override]
    public function getAtLeastPartiallyCoveredLines(ReportWaypoint $waypoint): int
    {
        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);
        $carryforwardTags = $this->getCarryforwardTags($waypoint);

        if (
            $ingestTimes === [] &&
            $uploads === [] &&
            $carryforwardTags === []
        ) {
            // Theres no point in checking for file coverage as theres no files to check against
            return 0;
        }

        $params = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                $carryforwardTags
            )
            ->set(
                QueryParameter::INGEST_PARTITIONS,
                [
                    ...$ingestTimes,
                    ...array_reduce(
                        $carryforwardTags,
                        static fn(array $ingestTimes, CarryforwardTag $carryforwardTag): array => [
                            ...$ingestTimes,
                            ...$carryforwardTag->getIngestTimes()
                        ],
                        []
                    )
                ]
            )
            ->set(
                QueryParameter::UPLOADS,
                $uploads
            );

        /** @var TotalCoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $totalCoverage->getPartial() + $totalCoverage->getCovered();
    }

    /**
     * @throws QueryException
     */
    #[Override]
    public function getUncoveredLines(ReportWaypoint $waypoint): int
    {
        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);
        $carryforwardTags = $this->getCarryforwardTags($waypoint);

        if (
            $ingestTimes === [] &&
            $uploads === [] &&
            $carryforwardTags === []
        ) {
            // Theres no point in checking for uncovered coverage as theres no files to check against
            return 0;
        }

        $params = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                $carryforwardTags
            )
            ->set(
                QueryParameter::INGEST_PARTITIONS,
                [
                    ...$ingestTimes,
                    ...array_reduce(
                        $carryforwardTags,
                        static fn(array $ingestTimes, CarryforwardTag $carryforwardTag): array => [
                            ...$ingestTimes,
                            ...$carryforwardTag->getIngestTimes()
                        ],
                        []
                    )
                ]
            )
            ->set(
                QueryParameter::UPLOADS,
                $uploads
            );

        /** @var TotalCoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $totalCoverage->getUncovered();
    }

    /**
     * @throws QueryException
     */
    #[Override]
    public function getCoveragePercentage(ReportWaypoint $waypoint): float
    {
        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);
        $carryforwardTags = $this->getCarryforwardTags($waypoint);

        if (
            $ingestTimes === [] &&
            $uploads === [] &&
            $carryforwardTags === []
        ) {
            // Theres no point in checking for coverage as theres no files to check against
            return 0;
        }

        $params = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                $carryforwardTags
            )
            ->set(
                QueryParameter::INGEST_PARTITIONS,
                [
                    ...$ingestTimes,
                    ...array_reduce(
                        $carryforwardTags,
                        static fn(array $ingestTimes, CarryforwardTag $carryforwardTag): array => [
                            ...$ingestTimes,
                            ...$carryforwardTag->getIngestTimes()
                        ],
                        []
                    )
                ]
            )
            ->set(
                QueryParameter::UPLOADS,
                $uploads
            );

        /** @var TotalCoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $totalCoverage->getCoveragePercentage();
    }

    /**
     * @throws QueryException
     */
    #[Override]
    public function getTagCoverage(ReportWaypoint $waypoint): TagCoverageCollectionQueryResult
    {
        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);
        $carryforwardTags = $this->getCarryforwardTags($waypoint);

        if (
            $ingestTimes === [] &&
            $uploads === [] &&
            $carryforwardTags === []
        ) {
            // Theres no point in checking for tag coverage as theres no files to check against
            return new TagCoverageCollectionQueryResult([]);
        }

        $params = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::CARRYFORWARD_TAGS,
                $carryforwardTags
            )
            ->set(
                QueryParameter::INGEST_PARTITIONS,
                [
                    ...$ingestTimes,
                    ...array_reduce(
                        $carryforwardTags,
                        static fn(array $ingestTimes, CarryforwardTag $carryforwardTag): array => [
                            ...$ingestTimes,
                            ...$carryforwardTag->getIngestTimes()
                        ],
                        []
                    )
                ]
            )
            ->set(
                QueryParameter::UPLOADS,
                $uploads
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
     * @throws AnalysisException
     */
    #[Override]
    public function getDiffCoveragePercentage(ReportWaypoint $waypoint): float|null
    {
        $diff = $this->getDiff($waypoint);
        if ($diff == []) {
            // Theres no point in checking diff coverage if theirs no diff
            return null;
        }

        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);

        if (
            $uploads === [] ||
            $ingestTimes === []
        ) {
            // Theres no point in checking diff coverage if theirs no uploads from
            // coverage with the up to date diff
            return null;
        }

        $params = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::LINES,
                $diff
            )
            ->set(
                QueryParameter::INGEST_PARTITIONS,
                $ingestTimes
            )
            ->set(
                QueryParameter::UPLOADS,
                $uploads
            );

        /**
         * @var TotalCoverageQueryResult $diffCoverage
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
     * @throws AnalysisException
     */
    #[Override]
    public function getLeastCoveredDiffFiles(
        ReportWaypoint $waypoint,
        int $limit = self::DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT
    ): FileCoverageCollectionQueryResult {
        $diff = $this->getDiff($waypoint);
        if ($diff == []) {
            // Theres no point in checking diff coverage if theirs no diff
            return new FileCoverageCollectionQueryResult([]);
        }

        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);

        if (
            $uploads === [] ||
            $ingestTimes === []
        ) {
            // Theres no point in checking diff coverage if theirs no uploads
            // from coverage with the up to date diff
            return new FileCoverageCollectionQueryResult([]);
        }

        $params = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::LINES,
                $diff
            )
            ->set(
                QueryParameter::LIMIT,
                $limit
            )
            ->set(
                QueryParameter::INGEST_PARTITIONS,
                $ingestTimes
            )
            ->set(
                QueryParameter::UPLOADS,
                $uploads
            );

        /**
         * @var FileCoverageCollectionQueryResult $files
         */
        $files = $this->queryService->runQuery(FileCoverageQuery::class, $params);

        return $files;
    }

    /**
     * @throws QueryException
     */
    public function getDiffUncoveredLines(ReportWaypoint $waypoint): int
    {
        $diff = $this->getDiff($waypoint);
        if ($diff == []) {
            // Theres no point in checking diff coverage if theirs no diff
            return 0;
        }

        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);

        if (
            $uploads === [] ||
            $ingestTimes === []
        ) {
            // Theres no point in checking diff coverage if theirs no uploads
            // from coverage with the up to date diff
            return 0;
        }

        $params = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::LINES,
                $diff
            )
            ->set(
                QueryParameter::INGEST_PARTITIONS,
                $ingestTimes
            )
            ->set(
                QueryParameter::UPLOADS,
                $uploads
            );

        /** @var TotalCoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $totalCoverage->getUncovered();
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
     * @throws AnalysisException
     */
    #[Override]
    public function getDiffLineCoverage(ReportWaypoint $waypoint): LineCoverageCollectionQueryResult
    {
        $diff = $this->getDiff($waypoint);
        if ($diff == []) {
            // Theres no point in checking diff coverage if theirs no diff
            return new LineCoverageCollectionQueryResult([]);
        }

        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);

        if (
            $uploads === [] ||
            $ingestTimes === []
        ) {
            // Theres no point in checking diff coverage if theirs no uploads
            // from coverage with the up to date diff
            return new LineCoverageCollectionQueryResult([]);
        }

        $params = QueryParameterBag::fromWaypoint($waypoint)
            ->set(
                QueryParameter::LINES,
                $diff
            )
            ->set(
                QueryParameter::INGEST_PARTITIONS,
                $ingestTimes
            )
            ->set(
                QueryParameter::UPLOADS,
                $uploads
            );

        /**
         * @var LineCoverageCollectionQueryResult $lines
         */
        $lines = $this->queryService->runQuery(LineCoverageQuery::class, $params);

        return $lines;
    }

    /**
     * @inheritDoc
     * @throws QueryException
     */
    #[Override]
    public function getCarryforwardTags(ReportWaypoint $waypoint): array
    {
        return $this->carryforwardTagService->getTagsToCarryforward(
            $waypoint,
            $this->getUploads($waypoint)
                ->getSuccessfulTags()
        );
    }

    /**
     * @return array<string, array<int, int>>
     * @throws AnalysisException
     */
    #[Override]
    public function getDiff(ReportWaypoint $waypoint): array
    {
        try {
            return $this->diffParser->get($waypoint);
        } catch (CommitDiffException $commitDiffException) {
            throw new AnalysisException(
                sprintf(
                    'Unable to get diff for %s',
                    $waypoint->getProvider()->value
                ),
                0,
                $commitDiffException
            );
        }
    }

    /**
     * @return array{commit: string, merged: bool, ref: string|null}[]
     * @throws AnalysisException
     */
    #[Override]
    public function getHistory(ReportWaypoint $waypoint, int $page = 1): array
    {
        try {
            return $this->commitHistoryService->getPrecedingCommits($waypoint, $page);
        } catch (CommitHistoryException $commitHistoryException) {
            throw new AnalysisException(
                sprintf(
                    'Failed to retrieve commit history for %s',
                    (string)$waypoint
                ),
                previous: $commitHistoryException
            );
        }
    }
}