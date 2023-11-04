<?php

namespace App\Tests\Service\Diff;

use App\Service\Diff\CachingDiffParserService;
use App\Service\Diff\DiffParserService;
use Packages\Event\Model\Upload;
use PHPUnit\Framework\TestCase;

class CachingDiffParserServiceTest extends TestCase
{
    public function testCachesRepeatedRequests(): void
    {
        $mockDiffParser = $this->createMock(DiffParserService::class);

        $mockDiffParser->expects($this->once())
            ->method('get')
            ->willReturn([]);

        $cachingDiffParser = new CachingDiffParserService($mockDiffParser);

        $mockUpload = $this->createMock(Upload::class);

        $this->assertEquals([], $cachingDiffParser->get($mockUpload));
        $this->assertEquals([], $cachingDiffParser->get($mockUpload));
    }

    public function testDoesNotCacheDifferentUploads(): void
    {
        $mockDiffParser = $this->createMock(DiffParserService::class);

        $mockDiffParser->expects($this->exactly(2))
            ->method('get')
            ->willReturn([]);

        $cachingDiffParser = new CachingDiffParserService($mockDiffParser);

        $this->assertEquals([], $cachingDiffParser->get($this->createMock(Upload::class)));
        $this->assertEquals([], $cachingDiffParser->get($this->createMock(Upload::class)));
    }
}
