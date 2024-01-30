<?php

namespace App\Service;

use App\Enum\QueryParameter;
use App\Exception\AnalysisException;
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
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use App\Service\Diff\CachingDiffParserService;
use App\Service\Diff\DiffParserServiceInterface;
use App\Service\History\CommitHistoryService;
use App\Service\History\CommitHistoryServiceInterface;
use DateTimeImmutable;
use Override;
use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CoverageAnalyserService implements CoverageAnalyserServiceInterface
{
    public function __construct(
        #[Autowire(service: CachingQueryService::class)]
        private readonly QueryServiceInterface $queryService,
        #[Autowire(service: CachingDiffParserService::class)]
        private readonly DiffParserServiceInterface $diffParser,
        #[Autowire(service: CommitHistoryService::class)]
        private readonly CommitHistoryServiceInterface $commitHistoryService,
        #[Autowire(service: CarryforwardTagService::class)]
        private readonly CarryforwardTagServiceInterface $carryforwardTagService
    ) {
    }

    /**
     * Get a waypoint for a particular point in time (or, a commit) for a provider.
     */
    #[Override]
    public function getWaypoint(
        Provider $provider,
        string $owner,
        string $repository,
        string $ref,
        string $commit,
        string|int|null $pullRequest = null
    ): ReportWaypoint {
        return new ReportWaypoint(
            provider: $provider,
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
    protected function getUploads(ReportWaypoint $waypoint): TotalUploadsQueryResult
    {
        /** @var TotalUploadsQueryResult $totalUploads */
        $totalUploads = $this->queryService->runQuery(
            TotalUploadsQuery::class,
            QueryParameterBag::fromWaypoint($waypoint)
        );

        return $totalUploads;
    }

    /**
     * @throws QueryException
     */
    protected function getSuccessfulUploads(ReportWaypoint $waypoint): array
    {
        return $this->getUploads($waypoint)
            ->getSuccessfulUploads();
    }

    /**
     * @return DateTimeImmutable[]
     *
     * @throws QueryException
     */
    protected function getSuccessfulIngestTimes(ReportWaypoint $waypoint): array
    {
        return $this->getUploads($waypoint)
            ->getSuccessfulIngestTimes();
    }

    /**
     * @throws QueryException
     */
    protected function getTotalLines(ReportWaypoint $waypoint): int
    {
        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);
        $carryforwardTags = $this->carryforwardTagService->getTagsToCarryforward(
            $waypoint,
            $this->getUploads($waypoint)
                ->getSuccessfulTags()
        );

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
    protected function getAtLeastPartiallyCoveredLines(ReportWaypoint $waypoint): int
    {
        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);
        $carryforwardTags = $this->carryforwardTagService->getTagsToCarryforward(
            $waypoint,
            $this->getUploads($waypoint)
                ->getSuccessfulTags()
        );

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
    protected function getUncoveredLines(ReportWaypoint $waypoint): int
    {
        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);
        $carryforwardTags = $this->carryforwardTagService->getTagsToCarryforward(
            $waypoint,
            $this->getUploads($waypoint)
                ->getSuccessfulTags()
        );

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
    protected function getCoveragePercentage(ReportWaypoint $waypoint): float
    {
        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);
        $carryforwardTags = $this->carryforwardTagService->getTagsToCarryforward(
            $waypoint,
            $this->getUploads($waypoint)
                ->getSuccessfulTags()
        );

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
    protected function getTagCoverage(ReportWaypoint $waypoint): TagCoverageCollectionQueryResult
    {
        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);
        $carryforwardTags = $this->carryforwardTagService->getTagsToCarryforward(
            $waypoint,
            $this->getUploads($waypoint)
                ->getSuccessfulTags()
        );

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
     */
    protected function getDiffCoveragePercentage(ReportWaypoint $waypoint): float|null
    {
        $diff = $this->getDiff($waypoint);
        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);

        if (
            $diff == [] ||
            $uploads === [] ||
            $ingestTimes === []
        ) {
            // Theres no point in checking diff coverage if theirs no diff or no
            // uploads from coverage with the up to date diff
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
     */
    protected function getLeastCoveredDiffFiles(
        ReportWaypoint $waypoint,
        int $limit = self::DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT
    ): FileCoverageCollectionQueryResult {
        $diff = $this->getDiff($waypoint);
        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);

        if (
            $diff == [] ||
            $uploads === [] ||
            $ingestTimes === []
        ) {
            // Theres no point in checking diff coverage if theirs no diff or no
            // uploads from coverage with the up to date diff
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
    protected function getDiffLineCoverage(ReportWaypoint $waypoint): LineCoverageCollectionQueryResult
    {
        $diff = $this->getDiff($waypoint);
        $uploads = $this->getSuccessfulUploads($waypoint);
        $ingestTimes = $this->getSuccessfulIngestTimes($waypoint);

        if (
            $diff == [] ||
            $uploads === [] ||
            $ingestTimes === []
        ) {
            // Theres no point in checking diff coverage if theirs no diff or no
            // uploads from coverage with the up to date diff
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
     * @return array<string, array<int, int>>
     */
    protected function getDiff(ReportWaypoint $waypoint): array
    {
        return $this->diffParser->get($waypoint);
    }

    /**
     * @return array{commit: string, merged: bool, ref: string|null}[]
     */
    protected function getHistory(ReportWaypoint $waypoint, int $page = 1): array
    {
        return $this->commitHistoryService->getPrecedingCommits($waypoint, $page);
    }
}
