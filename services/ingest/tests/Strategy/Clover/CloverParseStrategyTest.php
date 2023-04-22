<?php

namespace App\Tests\Strategy\Clover;

use App\Strategy\Clover\CloverParseStrategy;
use App\Strategy\ParseStrategyInterface;
use App\Tests\Strategy\AbstractParseStrategyTestCase;

class CloverParseStrategyTest extends AbstractParseStrategyTestCase
{
    public static function coverageFilesDataProvider(): array
    {
        return [
            ...parent::parseCoverageFixtures(__DIR__ . '/../../Fixture/Clover', 'xml'),
            'Does not handle invalid file' => [
                'invalid-file-content',
                false,
                []
            ]
        ];
    }

    protected function getParserStrategy(): ParseStrategyInterface
    {
        return new CloverParseStrategy();
    }
}
