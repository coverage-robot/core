<?php

namespace App\Tests\Strategy\Clover;

use App\Strategy\Clover\CloverParseStrategy;
use App\Strategy\ParseStrategyInterface;
use App\Tests\Strategy\AbstractParseStrategyTest;

class CloverParseStrategyTest extends AbstractParseStrategyTest
{
    public static function coverageFilesDataProvider(): array
    {
        return parent::parseCoverageFixtures(__DIR__ . "/../../Fixture/Clover", "xml");
    }

    protected function getParserStrategy(): ParseStrategyInterface
    {
        return new CloverParseStrategy();
    }
}
