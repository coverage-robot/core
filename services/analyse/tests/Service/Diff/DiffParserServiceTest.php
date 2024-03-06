<?php

namespace App\Tests\Service\Diff;

use App\Exception\CommitDiffException;
use App\Model\ReportWaypoint;
use App\Service\Diff\DiffParserService;
use App\Service\Diff\DiffParserServiceInterface;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\TestCase;

final class DiffParserServiceTest extends TestCase
{
    public function testGetUsingValidProvider(): void
    {
        $diff = [
            'file-1' => [1, 2, 3],
            'file-2' => [4, 5, 6],
        ];

        $mockParser = $this->createMock(DiffParserServiceInterface::class);
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
                    provider: Provider::GITHUB,
                    owner: 'owner',
                    repository: 'repository',
                    ref: 'ref',
                    commit: 'commit',
                    pullRequest: 12,
                    history: [],
                    diff: []
                )
            )
        );
    }

    public function testGetUsingInvalidProvider(): void
    {
        $mockParser = $this->createMock(DiffParserServiceInterface::class);
        $mockParser->expects($this->never())
            ->method('get');

        $diffParser = new DiffParserService(
            [
                'a-different-provider' => $mockParser,
            ]
        );

        $this->expectException(CommitDiffException::class);

        $diffParser->get(
            new ReportWaypoint(
                provider: Provider::GITHUB,
                owner: 'owner',
                repository: 'repository',
                ref: 'ref',
                commit: 'commit',
                history: [],
                diff: [],
                pullRequest: 12
            )
        );
    }
}
