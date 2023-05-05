<?php

namespace App\Tests\Service;

use App\Client\BigQueryClient;
use App\Model\Upload;
use App\Service\CoverageAnalyserService;
use Google\Cloud\BigQuery\QueryJobConfiguration;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\Core\Iterator\ItemIterator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CoverageAnalyserServiceTest extends TestCase
{
    public function testAnalyse(): void
    {
        $mockResultsIterator = $this->createMock(ItemIterator::class);
        $mockResultsIterator->method("current")
            ->willReturn([]);

        $mockQueryResults = $this->createMock(QueryResults::class);
        $mockQueryResults->expects($this->once())
            ->method("rows")
            ->willReturn($mockResultsIterator);

        $mockBigQueryClient = $this->createMock(BigQueryClient::class);
        $mockBigQueryClient->expects($this->once())
            ->method("query")
            ->willReturn($this->createMock(QueryJobConfiguration::class));

        $mockBigQueryClient->expects($this->once())
            ->method("runQuery")
            ->willReturn($mockQueryResults);


        $analyserService = new CoverageAnalyserService(
            new BigQueryClient(),
            new NullLogger()
        );

        $analyserService->analyse(
            new Upload([
                'uploadId' => 'mock-uuid',
                'commit' => '6fc03961c51e4b5fb91f423ebdfd830b5fd11ed4',
                'owner' => 'ryanmab',
                'repository' => 'portfolio',
                'pullRequest' => 1242
            ])
        );
    }
}
