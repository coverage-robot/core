<?php

namespace App\Model;

use App\Model\QueryResult\IntegerQueryResult;
use App\Model\QueryResult\TotalCoverageQueryResult;
use App\Model\QueryResult\TotalLineCoverageQueryResult;
use App\Model\QueryResult\TotalTagCoverageQueryResult;
use App\Query\TotalCoverageQuery;
use App\Query\TotalLineCoverageQuery;
use App\Query\TotalTagCoverageQuery;
use App\Query\TotalUploadsQuery;
use App\Service\QueryService;

class PublishableCoverageData implements PublishableCoverageDataInterface
{
    public function __construct(
        protected readonly QueryService $queryService,
        protected readonly Upload $upload
    ) {
    }

    public function getTotalUploads(): int
    {
        /** @var IntegerQueryResult $totalUploads */
        $totalUploads = $this->queryService->runQuery(TotalUploadsQuery::class, $this->upload);

        return $totalUploads->getResult();
    }

    public function getTotalLines(): int
    {
        /** @var TotalCoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $this->upload);

        return $totalCoverage->getLines();
    }

    public function getAtLeastPartiallyCoveredLines(): int
    {
        /** @var TotalCoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $this->upload);

        return $totalCoverage->getPartial() + $totalCoverage->getCovered();
    }

    public function getUncoveredLines(): int
    {
        /** @var TotalCoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $this->upload);

        return $totalCoverage->getUncovered();
    }

    public function getCoveragePercentage(): float
    {
        /** @var TotalCoverageQueryResult $totalCoverage */
        $totalCoverage = $this->queryService->runQuery(TotalCoverageQuery::class, $this->upload);

        return $totalCoverage->getCoveragePercentage();
    }

    public function getTagCoverage(): TotalTagCoverageQueryResult
    {
        /** @var TotalTagCoverageQueryResult $tags */
        $tags = $this->queryService->runQuery(TotalTagCoverageQuery::class, $this->upload);

        return $tags;
    }

    public function getLineCoverage(): TotalLineCoverageQueryResult
    {
        /**
         * @var TotalLineCoverageQueryResult $lines
         */
        $lines = $this->queryService->runQuery(TotalLineCoverageQuery::class, $this->upload);

        return $lines;
    }
}
