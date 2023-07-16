<?php

namespace App\Tests\Service;

use App\Model\CachedPublishableCoverageData;
use App\Service\CoverageAnalyserService;
use App\Service\Diff\DiffParserService;
use App\Service\QueryService;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\TestCase;

class CoverageAnalyserServiceTest extends TestCase
{
    public function testAnalyse(): void
    {
        $coverageAnalyserService = new CoverageAnalyserService(
            $this->createMock(QueryService::class),
            $this->createMock(DiffParserService::class),
        );

        $data = $coverageAnalyserService->analyse($this->createMock(Upload::class));

        $this->assertInstanceOf(
            CachedPublishableCoverageData::class,
            $data
        );
    }
}
