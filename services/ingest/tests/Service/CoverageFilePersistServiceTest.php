<?php

namespace App\Tests\Service;

use App\Exception\PersistException;
use App\Model\Project;
use App\Service\CoverageFilePersistService;
use App\Service\Persist\BigQueryPersistService;
use App\Service\Persist\S3PersistService;
use PHPUnit\Framework\TestCase;

class CoverageFilePersistServiceTest extends TestCase
{
    public function testPersistSuccessfully(): void
    {
        $mockS3PersistService = $this->createMock(S3PersistService::class);
        $mockS3PersistService->expects($this->once())
            ->method("persist")
            ->willReturn(true);

        $mockBigQueryPersistService = $this->createMock(BigQueryPersistService::class);
        $mockBigQueryPersistService->expects($this->once())
            ->method("persist")
            ->willReturn(true);

        $coverageFilePersistService = new CoverageFilePersistService([
            $mockS3PersistService,
            $mockBigQueryPersistService
        ]);

        $this->assertTrue(
            $coverageFilePersistService->persist(
                $this->createMock(Project::class),
                "mock-uuid"
            )
        );
    }

    public function testPersistPartialFailure(): void
    {
        $mockS3PersistService = $this->createMock(S3PersistService::class);
        $mockS3PersistService->expects($this->once())
            ->method("persist")
            ->willThrowException(new PersistException());

        $mockBigQueryPersistService = $this->createMock(BigQueryPersistService::class);
        $mockBigQueryPersistService->expects($this->once())
            ->method("persist")
            ->willReturn(true);

        $coverageFilePersistService = new CoverageFilePersistService([
            $mockS3PersistService,
            $mockBigQueryPersistService
        ]);

        $this->assertFalse(
            $coverageFilePersistService->persist(
                $this->createMock(Project::class),
                "mock-uuid"
            )
        );
    }
}
