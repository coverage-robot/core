<?php

namespace App\Tests\Service;

use App\Exception\ParseException;
use App\Service\CoverageFileParserService;
use App\Strategy\Clover\CloverParseStrategy;
use App\Strategy\Lcov\LcovParseStrategy;
use Packages\Models\Enum\CoverageFormat;
use Packages\Models\Model\Coverage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use stdClass;

class CoverageFileParserServiceTest extends TestCase
{
    private const STRATEGIES = [
        CloverParseStrategy::class,
        LcovParseStrategy::class
    ];

    #[DataProvider('strategyDataProvider')]
    public function testParsingSupportedFiles(string $expectedStrategy): void
    {
        $coverage = new Coverage(CoverageFormat::CLOVER, 'mock/project/root');

        $mockedStrategies = [];
        foreach (self::STRATEGIES as $strategy) {
            $mockStrategy = $this->createMock($strategy);
            $mockStrategy->expects($this->atMost(1))
                ->method('supports')
                ->with('mock-file')
                ->willReturn($expectedStrategy === $strategy);

            $mockStrategy->expects($this->exactly($expectedStrategy === $strategy ? 1 : 0))
                ->method('parse')
                ->with('mock-path', 'mock-file')
                ->willReturn($coverage);

            $mockedStrategies[] = $mockStrategy;
        }

        $coverageFileParserService = new CoverageFileParserService($mockedStrategies, new NullLogger());
        $this->assertEquals($coverage, $coverageFileParserService->parse('mock-path', 'mock-file'));
    }

    public function testInvalidParser(): void
    {
        $mockParser = $this->createMock(CloverParseStrategy::class);
        $mockParser->expects($this->atMost(1))
            ->method('supports')
            ->with('mock-file')
            ->willReturn(false);

        $invalidParser = $this->createMock(stdClass::class);

        $coverageFileParserService = new CoverageFileParserService(
            [
                $mockParser,
                $invalidParser
            ],
            new NullLogger()
        );

        $this->expectException(ParseException::class);

        $coverageFileParserService->parse('mock-path', 'mock-file');
    }

    public static function strategyDataProvider(): array
    {
        return array_map(static fn(string $strategy) => [$strategy], self::STRATEGIES);
    }
}
