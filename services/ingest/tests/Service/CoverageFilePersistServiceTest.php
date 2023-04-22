<?php

namespace App\Tests\Service;

use App\Enum\CoverageFormatEnum;
use App\Model\ProjectCoverage;
use App\Service\CoverageFilePersistService;
use AsyncAws\S3\S3Client;
use PHPUnit\Framework\TestCase;

class CoverageFilePersistServiceTest extends TestCase
{
    public function testPersistToS3()
    {
        $mockS3Client = $this->createMock(S3Client::class);

        $coverageFilePersistService = new CoverageFilePersistService($mockS3Client);
        $coverageFilePersistService->persistToS3("mock-bucket", "mock-object.json", new ProjectCoverage(CoverageFormatEnum::CLOVER, new \DateTimeImmutable()));
    }
}
