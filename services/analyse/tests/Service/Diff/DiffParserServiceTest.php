<?php

namespace App\Tests\Service\Diff;

use App\Model\ReportWaypoint;
use App\Service\Diff\DiffParserService;
use App\Service\Diff\Github\GithubDiffParserService;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DiffParserServiceTest extends TestCase
{
    public function testGetUsingValidProvider(): void
    {
        $diff = [
            'file-1' => [1, 2, 3],
            'file-2' => [4, 5, 6],
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
                new ReportWaypoint(
                    Provider::GITHUB,
                    'owner',
                    'repository',
                    'ref',
                    'commit',
                    12,
                    [],
                    []
                )
            )
        );
    }

    public function testGetUsingInvalidProvider(): void
    {
        $mockParser = $this->createMock(GithubDiffParserService::class);
        $mockParser->expects($this->never())
            ->method('get');

        $diffParser = new DiffParserService(
            [
                'a-different-provider' => $mockParser,
            ]
        );

        $this->expectException(RuntimeException::class);

        $diffParser->get(
            new ReportWaypoint(
                Provider::GITHUB,
                'owner',
                'repository',
                'ref',
                'commit',
                12,
                [],
                []
            )
        );
    }
}
