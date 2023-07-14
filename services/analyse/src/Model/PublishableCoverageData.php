<?php

namespace App\Model;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Query\FileCoverageQuery;
use App\Query\LineCoverageQuery;
use App\Query\Result\CoverageQueryResult;
use App\Query\Result\IntegerQueryResult;
use App\Query\Result\MultiFileCoverageQueryResult;
use App\Query\Result\MultiLineCoverageQueryResult;
use App\Query\Result\MultiTagCoverageQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalTagCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\DiffParserService;
use App\Service\QueryService;
use Packages\Models\Model\Upload;

class PublishableCoverageData implements PublishableCoverageDataInterface
{
    public function __construct(
        protected readonly QueryService $queryService,
        protected readonly DiffParserService $diffParser,
        protected readonly Upload $upload
    ) {
    }

    /**
     * @throws QueryException
     */
    public function getTotalUploads(): int
    {
        /** @var IntegerQueryResult $totalUploads */
        $totalUploads = $this->queryService->runQuery(TotalUploadsQuery::class, QueryParameterBag::fromUpload($this->upload));

        return $totalUploads->getResult();
    }

    /**
     * @throws QueryException
     */
    public function getTotalLines(): int
    {
        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, QueryParameterBag::fromUpload($this->upload));

        return $totalCoverage->getLines();
    }

    /**
     * @throws QueryException
     */
    public function getAtLeastPartiallyCoveredLines(): int
    {
        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, QueryParameterBag::fromUpload($this->upload));

        return $totalCoverage->getPartial() + $totalCoverage->getCovered();
    }

    /**
     * @throws QueryException
     */
    public function getUncoveredLines(): int
    {
        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, QueryParameterBag::fromUpload($this->upload));

        return $totalCoverage->getUncovered();
    }

    /**
     * @throws QueryException
     */
    public function getCoveragePercentage(): float
    {
        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, QueryParameterBag::fromUpload($this->upload));

        return $totalCoverage->getCoveragePercentage();
    }

    /**
     * @throws QueryException
     */
    public function getTagCoverage(): MultiTagCoverageQueryResult
    {
        /** @var MultiTagCoverageQueryResult $tags */
        $tags = $this->queryService->runQuery(TotalTagCoverageQuery::class, QueryParameterBag::fromUpload($this->upload));

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

        /**
         * @var CoverageQueryResult $diffCoverage
         */
        $diffCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $params);

        return $diffCoverage->getCoveragePercentage();
    }

    /**
     * @throws QueryException
     */
    public function getLeastCoveredDiffFiles(int $limit): MultiFileCoverageQueryResult
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

        /**
         * @var MultiFileCoverageQueryResult $files
         */
        $files = $this->queryService->runQuery(FileCoverageQuery::class, $params);

        return $files;
    }

    /**
     * @throws QueryException
     */
    public function getDiffLineCoverage(): MultiLineCoverageQueryResult
    {
        $params = QueryParameterBag::fromUpload($this->upload);
        $params->set(
            QueryParameter::LINE_SCOPE,
            $this->diffParser->get($this->upload)
        );

        /**
         * @var MultiLineCoverageQueryResult $lines
         */
        $lines = $this->queryService->runQuery(LineCoverageQuery::class, $params);

        return $lines;
    }
}
