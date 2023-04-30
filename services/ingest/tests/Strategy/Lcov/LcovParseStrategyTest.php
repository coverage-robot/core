<?php

namespace App\Tests\Strategy\Lcov;

use App\Strategy\Lcov\LcovParseStrategy;
use App\Strategy\ParseStrategyInterface;
use App\Tests\Strategy\AbstractParseStrategyTestCase;
use Psr\Log\NullLogger;

class LcovParseStrategyTest extends AbstractParseStrategyTestCase
{
    public static function coverageFilesDataProvider(): array
    {
        return [
            ...parent::parseCoverageFixtures(__DIR__ . '/../../Fixture/Lcov', 'info'),
            'Does not handle invalid file' => [
                'invalid-file-content',
                false,
                []
            ]
        ];
    }

    protected function getParserStrategy(): ParseStrategyInterface
    {
        return new LcovParseStrategy(new NullLogger());
    }
}
