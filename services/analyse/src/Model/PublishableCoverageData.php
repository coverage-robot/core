<?php

namespace App\Model;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Query\FileCoverageQuery;
use App\Query\LineCoverageQuery;
use App\Query\Result\CoverageQueryResult;
use App\Query\Result\FileCoverageCollectionQueryResult;
use App\Query\Result\IntegerQueryResult;
use App\Query\Result\LineCoverageCollectionQueryResult;
use App\Query\Result\TagCoverageCollectionQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalTagCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\Carryforward\CarryforwardTagServiceInterface;
use App\Service\Diff\DiffParserServiceInterface;
use App\Service\QueryService;
use Packages\Models\Model\Upload;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PublishableCoverageData implements PublishableCoverageDataInterface
{
    public function __construct(
        protected readonly QueryService $queryService,
        #[Autowire(service: 'App\Service\Diff\CachingDiffParserService')]
        protected readonly DiffParserServiceInterface $diffParser,
        #[Autowire(service: 'App\Service\Carryforward\CachingCarryforwardTagService')]
        protected readonly CarryforwardTagServiceInterface $carryforwardTagService,
        protected readonly Upload $upload
    ) {
    }

    /**
     * @throws QueryException
     */
    public function getTotalUploads(): int
    {
        /** @var IntegerQueryResult $totalUploads */
        $totalUploads = $this->queryService->runQuery(
            TotalUploadsQuery::class,
            QueryParameterBag::fromUpload($this->upload)
        );

        return $totalUploads->getResult();
    }

    /**
     * @throws QueryException
     */
    public function getTotalLines(): int
    {
        $params = QueryParameterBag::fromUpload($this->upload);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward($this->upload)
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
        $params = QueryParameterBag::fromUpload($this->upload);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward($this->upload)
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
        $params = QueryParameterBag::fromUpload($this->upload);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward($this->upload)
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
        $params = QueryParameterBag::fromUpload($this->upload);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward($this->upload)
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
        $params = QueryParameterBag::fromUpload($this->upload);
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward($this->upload)
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
        $params = QueryParameterBag::fromUpload($this->upload);
        $params->set(
            QueryParameter::LINE_SCOPE,
            $this->diffParser->get($this->upload)
        );
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward($this->upload)
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
    public function getLeastCoveredDiffFiles(int $limit): FileCoverageCollectionQueryResult
    {
        $params = QueryParameterBag::fromUpload($this->upload);
        $params->set(
            QueryParameter::LINE_SCOPE,
            $this->diffParser->get($this->upload)
        );
        $params->set(
            QueryParameter::LIMIT,
            $limit
        );
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward($this->upload)
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
        $params = QueryParameterBag::fromUpload($this->upload);
        $params->set(
            QueryParameter::LINE_SCOPE,
            $this->diffParser->get($this->upload)
        );
        $params->set(
            QueryParameter::CARRYFORWARD_TAGS,
            $this->carryforwardTagService->getTagsToCarryforward($this->upload)
        );

        /**
         * @var LineCoverageCollectionQueryResult $lines
         */
        $lines = $this->queryService->runQuery(LineCoverageQuery::class, $params);

        return $lines;
    }
}
