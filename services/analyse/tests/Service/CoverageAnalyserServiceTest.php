<?php

namespace App\Tests\Service;

use App\Client\BigQueryClient;
use App\Service\CoverageAnalyserService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CoverageAnalyserServiceTest extends TestCase
{
    public function testAnalyse()
    {
        $analyserService = new CoverageAnalyserService(
            new BigQueryClient(),
            new NullLogger()
        );

        $analyserService->analyse('');
    }
}
