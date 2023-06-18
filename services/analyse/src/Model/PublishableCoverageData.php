<?php

namespace App\Model;

use App\Enum\QueryParameter;
use App\Exception\QueryException;
use App\Model\QueryResult\CoverageQueryResult;
use App\Model\QueryResult\IntegerQueryResult;
use App\Model\QueryResult\MultiFileCoverageQueryResult;
use App\Model\QueryResult\MultiLineCoverageQueryResult;
use App\Model\QueryResult\MultiTagCoverageQueryResult;
use App\Query\FileCoverageQuery;
use App\Query\LineCoverageQuery;
use App\Query\TotalCoverageQuery;
use App\Query\TotalTagCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\DiffParserService;
use App\Service\QueryService;
use Packages\Models\Model\Upload;

class PublishableCoverageData implements PublishableCoverageDataInterface
{
    public function __construct(
        protected readonly QueryService      $queryService,
        protected readonly DiffParserService $diffReader,
        protected readonly Upload            $upload
    ) {
    }

    /**
     * @throws QueryException
     */
    public function getTotalUploads(): int
    {
        /** @var IntegerQueryResult $totalUploads */
        $totalUploads = $this->queryService->runQuery(TotalUploadsQuery::class, $this->upload);

        return $totalUploads->getResult();
    }

    /**
     * @throws QueryException
     */
    public function getTotalLines(): int
    {
        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $this->upload);

        return $totalCoverage->getLines();
    }

    /**
     * @throws QueryException
     */
    public function getAtLeastPartiallyCoveredLines(): int
    {
        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $this->upload);

        return $totalCoverage->getPartial() + $totalCoverage->getCovered();
    }

    /**
     * @throws QueryException
     */
    public function getUncoveredLines(): int
    {
        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $this->upload);

        return $totalCoverage->getUncovered();
    }

    /**
     * @throws QueryException
     */
    public function getCoveragePercentage(): float
    {
        /** @var CoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $this->upload);

        return $totalCoverage->getCoveragePercentage();
    }

    /**
     * @throws QueryException
     */
    public function getTagCoverage(): MultiTagCoverageQueryResult
    {
        /** @var MultiTagCoverageQueryResult $tags */
        $tags = $this->queryService->runQuery(TotalTagCoverageQuery::class, $this->upload);

        return $tags;
    }

    /**
     * @throws QueryException
     */
    public function getDiffCoveragePercentage(): float
    {
        $params = new QueryParameterBag();
        $params->set(
            QueryParameter::LINE_SCOPE,
            $this->diffReader->get($this->upload)
        );

        /**
         * @var CoverageQueryResult $diffCoverage
         */
        $diffCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $this->upload, $params);

        return $diffCoverage->getCoveragePercentage();
    }

    /**
    * @throws QueryException
    */
    public function getLeastCoveredDiffFiles(int $limit): MultiFileCoverageQueryResult
    {
        $params = new QueryParameterBag();
        $params->set(
            QueryParameter::LINE_SCOPE,
            $this->diffReader->get($this->upload)
        );
        $params->set(
            QueryParameter::LIMIT,
            $limit
        );

        /**
         * @var MultiFileCoverageQueryResult $files
         */
        $files = $this->queryService->runQuery(FileCoverageQuery::class, $this->upload, $params);

        return $files;
    }

    /**
     * @throws QueryException
     */
    public function getDiffLineCoverage(): MultiLineCoverageQueryResult
    {
        $params = new QueryParameterBag();
        $params->set(
            QueryParameter::LINE_SCOPE,
            $this->diffReader->get($this->upload)
        );

        /**
         * @var MultiLineCoverageQueryResult $lines
         */
        $lines = $this->queryService->runQuery(LineCoverageQuery::class, $this->upload, $params);

        return $lines;
    }
}
