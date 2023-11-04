<?php

namespace App\Model;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
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
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use App\Service\Diff\DiffParserServiceInterface;
use App\Service\QueryServiceInterface;
use DateTimeImmutable;
use Packages\Event\Model\EventInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PublishableCoverageData implements PublishableCoverageDataInterface
{
    public const DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT = 10;

    public function __construct(
        #[Autowire(service: 'App\Service\CachingQueryService')]
        protected readonly QueryServiceInterface $queryService,
        #[Autowire(service: 'App\Service\Diff\CachingDiffParserService')]
        protected readonly DiffParserServiceInterface $diffParser,
        #[Autowire(service: 'App\Service\Carryforward\CachingCarryforwardTagService')]
        protected readonly CarryforwardTagServiceInterface $carryforwardTagService,
        protected readonly EventInterface $event
    ) {
    }

    public function getUploads(): TotalUploadsQueryResult
    {
        /** @var TotalUploadsQueryResult $totalUploads */
        $totalUploads = $this->queryService->runQuery(
            TotalUploadsQuery::class,
            QueryParameterBag::fromEvent($this->event)
        );

        return $totalUploads;
    }

    /**
     * @throws QueryException
     */
    public function getSuccessfulUploads(): array
    {
        return $this->getUploads()
            ->getSuccessfulUploads();
    }

    public function getLatestSuccessfulUpload(): DateTimeImmutable|null
    {
        return $this->getUploads()
            ->getLatestSuccessfulUpload();
    }

    /**
     * @throws QueryException
     */
    public function getTotalLines(): int
    {
        $params = QueryParameterBag::fromEvent($this->event);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward(
                $this->event,
                $this->getUploads()->getSuccessfulTags()
            )
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads()
        );

        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $totalCoverage->getLines();
    }

    /**
     * @throws QueryException
     */
    public function getAtLeastPartiallyCoveredLines(): int
    {
        $params = QueryParameterBag::fromEvent($this->event);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward(
                $this->event,
                $this->getUploads()->getSuccessfulTags()
            )
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads()
        );

        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $totalCoverage->getPartial() + $totalCoverage->getCovered();
    }

    /**
     * @throws QueryException
     */
    public function getUncoveredLines(): int
    {
        $params = QueryParameterBag::fromEvent($this->event);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward(
                $this->event,
                $this->getUploads()->getSuccessfulTags()
            )
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads()
        );

        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $totalCoverage->getUncovered();
    }

    /**
     * @throws QueryException
     */
    public function getCoveragePercentage(): float
    {
        $params = QueryParameterBag::fromEvent($this->event);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward(
                $this->event,
                $this->getUploads()->getSuccessfulTags()
            )
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads()
        );

        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $totalCoverage->getCoveragePercentage();
    }

    /**
     * @throws QueryException
     */
    public function getTagCoverage(): TagCoverageCollectionQueryResult
    {
        $params = QueryParameterBag::fromEvent($this->event);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward(
                $this->event,
                $this->getUploads()->getSuccessfulTags()
            )
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads()
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
    public function getDiffCoveragePercentage(): float
    {
        $params = QueryParameterBag::fromEvent($this->event);
        $params->set(
            QueryParameter::LINE_SCOPE,
            $this->diffParser->get($this->event)
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads()
        );

        /**
         * @var CoverageQueryResult $diffCoverage
         */
        $diffCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

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
    public function getLeastCoveredDiffFiles(
        int $limit = self::DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT
    ): FileCoverageCollectionQueryResult {
        $params = QueryParameterBag::fromEvent($this->event);
        $params->set(
            QueryParameter::LINE_SCOPE,
            $this->diffParser->get($this->event)
        );
        $params->set(
            QueryParameter::LIMIT,
            $limit
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads()
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
    public function getDiffLineCoverage(): LineCoverageCollectionQueryResult
    {
        $params = QueryParameterBag::fromEvent($this->event);
        $params->set(
            QueryParameter::LINE_SCOPE,
            $this->diffParser->get($this->event)
        );
        $params->set(
            QueryParameter::UPLOADS_SCOPE,
            $this->getSuccessfulUploads()
        );

        /**
         * @var LineCoverageCollectionQueryResult $lines
         */
        $lines = $this->queryService->runQuery(LineCoverageQuery::class, $params);

        return $lines;
    }
}
