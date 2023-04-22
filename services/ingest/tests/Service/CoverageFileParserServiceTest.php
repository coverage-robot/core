<?php

namespace App\Tests\Service;

use App\Enum\CoverageFormatEnum;
use App\Model\ProjectCoverage;
use App\Service\CoverageFileParserService;
use App\Strategy\Clover\CloverParseStrategy;
use App\Strategy\Lcov\LcovParseStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CoverageFileParserServiceTest extends TestCase
{
    private const STRATEGIES = [
        CoverageFormatEnum::CLOVER->name => CloverParseStrategy::class,
        CoverageFormatEnum::LCOV->name => LcovParseStrategy::class
    ];

    #[DataProvider('coverageFilePathDataProvider')]
    public function testParsingSupportedFiles(string $path, string $expectedStrategy)
    {
        $coverageFile = file_get_contents($path);
        $coverage = new ProjectCoverage(CoverageFormatEnum::CLOVER);

        $mockedStrategies = [];
        foreach (self::STRATEGIES as $strategy) {
            $mockStrategy = $this->createMock($strategy);
            $mockStrategy->expects($this->atMost(1))
                ->method("supports")
                ->with($coverageFile)
                ->willReturn($expectedStrategy === $strategy);

            $mockStrategy->expects($this->exactly($expectedStrategy === $strategy ? 1 : 0))
                ->method("parse")
                ->with($coverageFile)
                ->willReturn($coverage);

            $mockedStrategies[] = $mockStrategy;
        }

        $coverageFileParserService = new CoverageFileParserService($mockedStrategies);
        $this->assertEquals($coverage, $coverageFileParserService->parse($coverageFile));
    }

    public static function coverageFilePathDataProvider(): array
    {
        return [
            "Clover (PHP variant)" => [
                __DIR__ . "/../Clover/complex-php-coverage.xml",
                self::STRATEGIES[CoverageFormatEnum::CLOVER->name]
            ],
            "Clover (Jest variant)" => [
                __DIR__ . "/../Clover/complex-jest-coverage.xml",
                self::STRATEGIES[CoverageFormatEnum::CLOVER->name]
            ],
            "Lcov" => [
                __DIR__ . "/../Lcov/complex.xml",
                self::STRATEGIES[CoverageFormatEnum::LCOV->name]
            ],
        ];
    }
}
