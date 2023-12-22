<?php

namespace App\Tests\Strategy\Lcov;

use App\Service\PathFixingService;
use App\Strategy\Lcov\LcovParseStrategy;
use App\Strategy\ParseStrategyInterface;
use App\Tests\Strategy\AbstractParseStrategyTestCase;
use Psr\Log\NullLogger;

class LcovParseStrategyTest extends AbstractParseStrategyTestCase
{
    public static function coverageFilesDataProvider(): iterable
    {
        yield from parent::parseCoverageFixtures(__DIR__ . '/../../Fixture/Lcov');

        yield 'Does not handle invalid file' => [
            'mock/project/root',
            'invalid-file-content',
            false
        ];
    }

    protected function getParserStrategy(): ParseStrategyInterface
    {
        return new LcovParseStrategy(
            new NullLogger(),
            new PathFixingService()
        );
    }
}
