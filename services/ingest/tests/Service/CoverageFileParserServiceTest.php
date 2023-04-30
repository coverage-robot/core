<?php

namespace App\Tests\Service;

use App\Enum\CoverageFormatEnum;
use App\Model\Project;
use App\Service\CoverageFileParserService;
use App\Strategy\Clover\CloverParseStrategy;
use App\Strategy\Lcov\LcovParseStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CoverageFileParserServiceTest extends TestCase
{
    private const STRATEGIES = [
        CloverParseStrategy::class,
        LcovParseStrategy::class
    ];

    #[DataProvider('strategyDataProvider')]
    public function testParsingSupportedFiles(string $expectedStrategy): void
    {
        $coverage = new Project(CoverageFormatEnum::CLOVER);

        $mockedStrategies = [];
        foreach (self::STRATEGIES as $strategy) {
            $mockStrategy = $this->createMock($strategy);
            $mockStrategy->expects($this->atMost(1))
                ->method('supports')
                ->with('mock-file')
                ->willReturn($expectedStrategy === $strategy);

            $mockStrategy->expects($this->exactly($expectedStrategy === $strategy ? 1 : 0))
                ->method('parse')
                ->with('mock-file')
                ->willReturn($coverage);

            $mockedStrategies[] = $mockStrategy;
        }

        $coverageFileParserService = new CoverageFileParserService($mockedStrategies, new NullLogger());
        $this->assertEquals($coverage, $coverageFileParserService->parse('mock-file'));
    }

    public static function strategyDataProvider(): array
    {
        return array_map(static fn(string $strategy) => [$strategy], self::STRATEGIES);
    }
}
