<?php

namespace App\Tests\Service;

use App\Model\CachingPublishableCoverageData;
use App\Service\Carryforward\CarryforwardTagService;
use App\Service\CoverageAnalyserService;
use App\Service\Diff\DiffParserService;
use App\Service\QueryService;
use Packages\Models\Model\Event\Upload;
use PHPUnit\Framework\TestCase;

class CoverageAnalyserServiceTest extends TestCase
{
    public function testAnalyse(): void
    {
        $coverageAnalyserService = new CoverageAnalyserService(
            $this->createMock(QueryService::class),
            $this->createMock(DiffParserService::class),
            $this->createMock(CarryforwardTagService::class)
        );

        $data = $coverageAnalyserService->analyse($this->createMock(Upload::class));

        $this->assertInstanceOf(
            CachingPublishableCoverageData::class,
            $data
        );
    }
}
