<?php

namespace App\Model;

use App\Exception\QueryException;
use App\Model\QueryResult\IntegerQueryResult;
use App\Model\QueryResult\TotalCoverageQueryResult;
use App\Model\QueryResult\TotalLineCoverageQueryResult;
use App\Model\QueryResult\TotalTagCoverageQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalLineCoverageQuery;
use App\Query\TotalTagCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\QueryService;
use Packages\Models\Model\Upload;

class PublishableCoverageData implements PublishableCoverageDataInterface
{
    public function __construct(
        protected readonly QueryService $queryService,
        protected readonly Upload $upload
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
        /** @var TotalCoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $this->upload);

        return $totalCoverage->getLines();
    }

    /**
     * @throws QueryException
     */
    public function getAtLeastPartiallyCoveredLines(): int
    {
        /** @var TotalCoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $this->upload);

        return $totalCoverage->getPartial() + $totalCoverage->getCovered();
    }

    /**
     * @throws QueryException
     */
    public function getUncoveredLines(): int
    {
        /** @var TotalCoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $this->upload);

        return $totalCoverage->getUncovered();
    }

    /**
     * @throws QueryException
     */
    public function getCoveragePercentage(): float
    {
        /** @var TotalCoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $this->upload);

        return $totalCoverage->getCoveragePercentage();
    }

    /**
     * @throws QueryException
     */
    public function getTagCoverage(): TotalTagCoverageQueryResult
    {
        /** @var TotalTagCoverageQueryResult $tags */
        $tags = $this->queryService->runQuery(TotalTagCoverageQuery::class, $this->upload);

        return $tags;
    }

    /**
     * @throws QueryException
     */
    public function getLineCoverage(): TotalLineCoverageQueryResult
    {
        /**
         * @var TotalLineCoverageQueryResult $lines
         */
        $lines = $this->queryService->runQuery(TotalLineCoverageQuery::class, $this->upload);

        return $lines;
    }
}
