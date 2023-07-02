<?php

namespace App\Tests\Service;

use App\Service\Diff\Github\GithubDiffParserService;
use App\Service\DiffParserService;
use Packages\Models\Enum\Provider;
use Packages\Models\Model\Upload;
use PHPUnit\Framework\TestCase;

class DiffParserServiceTest extends TestCase
{
    public function testGetUsingValidProvider(): void
    {
        $diff = [
            'file-1' => [1,2,3],
            'file-2' => [4,5,6],
        ];

        $mockParser = $this->createMock(GithubDiffParserService::class);
        $mockParser->expects($this->once())
            ->method('get')
            ->willReturn($diff);

        $diffParser = new DiffParserService(
            [
                Provider::GITHUB->value => $mockParser,
            ]
        );

        $this->assertEquals(
            $diff,
            $diffParser->get(
                Upload::from([
                    'provider' => Provider::GITHUB->value,
                    'owner' => 'owner',
                    'repository' => 'repository',
                    'commit' => 'commit',
                    'uploadId' => 'uploadId',
                    'ref' => 'ref',
                    'parent' => [],
                    'tag' => 'tag',
                ])
            )
        );
    }
}
