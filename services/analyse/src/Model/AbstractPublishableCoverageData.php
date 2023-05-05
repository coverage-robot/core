<?php

namespace App\Model;

use App\Query\CommitLineCoverageQuery;
use App\Query\TotalCommitCoverageQuery;
use App\Service\QueryService;

abstract class AbstractPublishableCoverageData implements PublishableCoverageDataInterface
{
    public function __construct(
        protected readonly QueryService $queryService,
        protected readonly Upload $upload
    ) {
    }

    public function getTotalCommitCoverage(): array
    {
        return $this->queryService->runQuery(TotalCommitCoverageQuery::class, $this->upload);
    }

    public function getCommitLineCoverage(): array
    {
        return $this->queryService->runQuery(CommitLineCoverageQuery::class, $this->upload);
    }
}
