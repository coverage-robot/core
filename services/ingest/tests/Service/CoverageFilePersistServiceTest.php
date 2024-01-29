<?php

namespace App\Tests\Service;

use App\Exception\PersistException;
use App\Model\Coverage;
use App\Service\CoverageFilePersistService;
use App\Service\Persist\PersistServiceInterface;
use Packages\Contracts\Format\CoverageFormat;
use Packages\Event\Model\Upload;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CoverageFilePersistServiceTest extends TestCase
{
    public function testPersistSuccessfully(): void
    {
        $mockPersistServiceOne = $this->createMock(PersistServiceInterface::class);
        $mockPersistServiceOne->expects($this->once())
            ->method('persist')
            ->willReturn(true);

        $mockPersistServiceTwo = $this->createMock(PersistServiceInterface::class);
        $mockPersistServiceTwo->expects($this->once())
            ->method('persist')
            ->willReturn(true);

        $coverageFilePersistService = new CoverageFilePersistService(
            [
                $mockPersistServiceOne,
                $mockPersistServiceTwo
            ],
            new NullLogger()
        );

        $this->assertTrue(
            $coverageFilePersistService->persist(
                $this->createMock(Upload::class),
                new Coverage(
                    sourceFormat: CoverageFormat::CLOVER,
                    root: 'mock-root'
                )
            )
        );
    }

    public function testPersistPartialFailure(): void
    {
        $mockPersistServiceOne = $this->createMock(PersistServiceInterface::class);
        $mockPersistServiceOne->expects($this->once())
            ->method('persist')
            ->willThrowException(new PersistException());

        $mockPersistServiceTwo = $this->createMock(PersistServiceInterface::class);
        $mockPersistServiceTwo->expects($this->once())
            ->method('persist')
            ->willReturn(true);

        $coverageFilePersistService = new CoverageFilePersistService(
            [
                $mockPersistServiceOne,
                $mockPersistServiceTwo
            ],
            new NullLogger()
        );

        $this->assertFalse(
            $coverageFilePersistService->persist(
                $this->createMock(Upload::class),
                new Coverage(
                    sourceFormat: CoverageFormat::CLOVER,
                    root: 'mock-root'
                )
            )
        );
    }
}
