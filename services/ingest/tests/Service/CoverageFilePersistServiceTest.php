<?php

namespace App\Tests\Service;

use App\Exception\PersistException;
use App\Model\Coverage;
use App\Service\CoverageFilePersistService;
use App\Service\Persist\GcsPersistService;
use App\Service\Persist\S3PersistService;
use Packages\Event\Model\Upload;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CoverageFilePersistServiceTest extends TestCase
{
    public function testPersistSuccessfully(): void
    {
        $mockS3PersistService = $this->createMock(S3PersistService::class);
        $mockS3PersistService->expects($this->once())
            ->method('persist')
            ->willReturn(true);

        $mockBigQueryPersistService = $this->createMock(GcsPersistService::class);
        $mockBigQueryPersistService->expects($this->once())
            ->method('persist')
            ->willReturn(true);

        $coverageFilePersistService = new CoverageFilePersistService(
            [
                $mockS3PersistService,
                $mockBigQueryPersistService
            ],
            new NullLogger()
        );

        $this->assertTrue(
            $coverageFilePersistService->persist(
                $this->createMock(Upload::class),
                $this->createMock(Coverage::class)
            )
        );
    }

    public function testPersistPartialFailure(): void
    {
        $mockS3PersistService = $this->createMock(S3PersistService::class);
        $mockS3PersistService->expects($this->once())
            ->method('persist')
            ->willThrowException(new PersistException());

        $mockBigQueryPersistService = $this->createMock(GcsPersistService::class);
        $mockBigQueryPersistService->expects($this->once())
            ->method('persist')
            ->willReturn(true);

        $coverageFilePersistService = new CoverageFilePersistService(
            [
                $mockS3PersistService,
                $mockBigQueryPersistService
            ],
            new NullLogger()
        );

        $this->assertFalse(
            $coverageFilePersistService->persist(
                $this->createMock(Upload::class),
                $this->createMock(Coverage::class)
            )
        );
    }
}
