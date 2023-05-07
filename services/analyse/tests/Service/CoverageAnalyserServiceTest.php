<?php

namespace App\Tests\Service;

use App\Model\CachedPublishableCoverageData;
use App\Model\Upload;
use App\Service\CoverageAnalyserService;
use App\Service\QueryService;
use PHPUnit\Framework\TestCase;

class CoverageAnalyserServiceTest extends TestCase
{
    public function testAnalyse(): void
    {
        $coverageAnalyserService = new CoverageAnalyserService($this->createMock(QueryService::class));

        $this->assertInstanceOf(
            CachedPublishableCoverageData::class,
            $coverageAnalyserService->analyse($this->createMock(Upload::class))
        );
    }
}
