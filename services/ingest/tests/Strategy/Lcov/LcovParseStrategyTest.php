<?php

namespace App\Tests\Strategy\Lcov;

use App\Strategy\Lcov\LcovParseStrategy;
use App\Strategy\ParseStrategyInterface;
use App\Tests\Strategy\AbstractParseStrategyTestCase;

class LcovParseStrategyTest extends AbstractParseStrategyTestCase
{
    public static function coverageFilesDataProvider(): iterable
    {
        yield from parent::parseCoverageFixtures(__DIR__ . '/../../Fixture/Lcov', 'info');

        yield 'Does not handle invalid file' => [
            'mock/project/root',
            'invalid-file-content',
            false,
            ''
        ];
    }

    protected function getParserStrategy(): ParseStrategyInterface
    {
        return $this->getContainer()->get(LcovParseStrategy::class);
    }
}
