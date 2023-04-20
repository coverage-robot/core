<?php

namespace App\Tests\Strategy\Lcov;

use App\Exception\ParseException;
use App\Strategy\Clover\CloverParseStrategy;
use App\Strategy\Lcov\LcovParseStrategy;
use App\Tests\Strategy\AbstractParseStrategyTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LcovParseStrategyTest extends AbstractParseStrategyTest
{
    #[DataProvider('fixturesDataProvider')]
    public function testSupports(string $contents, bool $expectedSupport): void
    {
        $parser = new LcovParseStrategy();
        $this->assertEquals($expectedSupport, $parser->supports($contents));
    }

    #[DataProvider('fixturesDataProvider')]
    public function testParse(string $contents, bool $expectedSupport, array $expectedCoverage): void
    {
        $parser = new LcovParseStrategy();
        if (!$expectedSupport) {
            $this->expectException(ParseException::class);
        }

        $projectCoverage = $parser->parse($contents);

        if ($expectedSupport) {
            $this->assertEquals(
                $expectedCoverage,
                json_decode(json_encode($projectCoverage), true)
            );
        }
    }

    public static function fixturesDataProvider(): array
    {
        return parent::parseFixturesDataProvider(__DIR__ . "/../../Fixture/Lcov", "info");
    }
}
