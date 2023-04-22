<?php

namespace App\Tests\Strategy\Lcov;

use App\Strategy\Lcov\LcovParseStrategy;
use App\Strategy\ParseStrategyInterface;
use App\Tests\Strategy\AbstractParseStrategyTest;

class LcovParseStrategyTest extends AbstractParseStrategyTest
{
    public static function coverageFilesDataProvider(): array
    {
        return parent::parseCoverageFixtures(__DIR__ . "/../../Fixture/Lcov", "info");
    }

    protected function getParserStrategy(): ParseStrategyInterface
    {
        return new LcovParseStrategy();
    }
}
