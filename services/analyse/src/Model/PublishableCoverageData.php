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
use App\Service\QueryService;
use DateTimeImmutable;
use Packages\Models\Model\Event\EventInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PublishableCoverageData implements PublishableCoverageDataInterface
{
    public const DEFAULT_LEAST_COVERED_DIFF_FILES_LIMIT = 10;

    public function __construct(
        protected readonly QueryService $queryService,
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

    /**
     * @throws QueryException
     */
    public function getPendingUploads(): array
    {
        return $this->getUploads()
            ->getPendingUploads();
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

        /**
         * @var CoverageQueryResult $diffCoverage
         */
        $diffCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $diffCoverage->getCoveragePercentage();
    }

    /**
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

        /**
         * @var FileCoverageCollectionQueryResult $files
         */
        $files = $this->queryService->runQuery(FileCoverageQuery::class, $params);

        return $files;
    }

    /**
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

        /**
         * @var LineCoverageCollectionQueryResult $lines
         */
        $lines = $this->queryService->runQuery(LineCoverageQuery::class, $params);

        return $lines;
    }
}
