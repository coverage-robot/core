<?php

namespace App\Model;

use App\Exception\QueryException;
use App\Query\CommitLineCoverageQuery;
use App\Query\TotalCommitCoverageQuery;
use App\Query\TotalCommitUploadsQuery;
use App\Service\QueryService;

/**
 * @psalm-import-type CommitCoverage from TotalCommitCoverageQuery
 * @psalm-import-type CommitLineCoverage from CommitLineCoverageQuery
 */
abstract class AbstractPublishableCoverageData implements PublishableCoverageDataInterface
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
        $totalUploads = $this->queryService->runQuery(TotalCommitUploadsQuery::class, $this->upload);

        if (is_int($totalUploads)) {
            return $totalUploads;
        }

        throw QueryException::typeMismatch(gettype($totalUploads), 'int');
    }

    /**
     * @return CommitCoverage
     * @throws QueryException
     */
    protected function getTotalCommitCoverage(): array
    {
        $commitCoverage = $this->queryService->runQuery(TotalCommitCoverageQuery::class, $this->upload);

        if (is_array($commitCoverage)) {
            /** @var CommitCoverage $commitCoverage */
            return $commitCoverage;
        }

        throw QueryException::typeMismatch(gettype($commitCoverage), 'array');
    }

    /**
     * @return CommitLineCoverage[]
     * @throws QueryException
     */
    public function getCommitLineCoverage(): array
    {
        $lineCoverage = $this->queryService->runQuery(CommitLineCoverageQuery::class, $this->upload);

        if (is_array($lineCoverage)) {
            /** @var CommitLineCoverage[] $lineCoverage */
            return $lineCoverage;
        }

        throw QueryException::typeMismatch(gettype($lineCoverage), 'array');
    }
}
