<?php

namespace App\Tests\Service;

use App\Client\BigQueryClient;
use App\Model\Upload;
use App\Query\TotalCommitCoverageQuery;
use App\Query\TotalCommitUploadsQuery;
use App\Service\QueryService;
use PHPUnit\Framework\TestCase;

class QueryServiceTest extends TestCase
{
    public function testRunQuery(): void
    {
        $queryService = new QueryService($this->createMock(BigQueryClient::class), [new TotalCommitCoverageQuery()]);
        $queryService->runQuery(TotalCommitCoverageQuery::class, $this->createMock(Upload::class));
    }

    public function testRunQueryTwo(): void
    {
        $queryService = new QueryService($this->createMock(BigQueryClient::class), [new TotalCommitUploadsQuery()]);
        $queryService->runQuery(TotalCommitUploadsQuery::class, $this->createMock(Upload::class));
    }
}
